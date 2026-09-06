import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ColumnLayout } from '@/components/data/column-layout';
import {
    defaultLayout,
    isDefaultLayout,
} from '@/components/data/column-layout';
import { update as updateAdminColumns } from '@/routes/admin/table-columns';
import { update as updateTenantColumns } from '@/routes/tenant/table-columns';

/** The part of a column definition this hook needs — see column-layout.ts. */
type Described = Parameters<typeof defaultLayout>[0][number];

/** How long to wait before saving, so dragging is one request rather than one per step. */
const SAVE_DELAY = 500;

/**
 * The reader's column layout for one list: seeded from what they saved, written back when
 * they change it.
 *
 * **The seed is a server prop, and that is what keeps SSR honest.** The table builds its
 * header row from this during render, so the layout has to be a value both sides already
 * agree on. Reading it from the browser after mount would render the declared columns
 * first and the saved ones a frame later — a visible jump on every list, and the exact
 * thing this codebase treats as a hard rule rather than a preference.
 *
 * Saving is deliberately not awaited and deliberately not shown. Nobody ticking a checkbox
 * is waiting for a round trip, and a toast for "your column preference was saved" is noise
 * about something the screen has already demonstrated.
 *
 * Beside the table kit rather than in `hooks/`, because it is the table's own business and
 * has no second consumer.
 */
export function useColumnLayout(
    tableKey: App.Enums.TableKey,
    columns: readonly Described[],
): [ColumnLayout, (next: ColumnLayout) => void] {
    const { tableColumns, tenant } = usePage().props;

    const [layout, setLayout] = useState<ColumnLayout>(() => {
        const saved = tableColumns?.[tableKey];

        return saved === undefined
            ? defaultLayout(columns)
            : // A saved layout names ids, and ids outlive nothing in particular — a
              // column can be removed or renamed under it. Reconciling against what the
              // page declares happens in toColumnOrder, so anything odd here is dropped
              // at render rather than needing a migration.
              { order: [...saved.order], hidden: [...saved.hidden] };
    });

    // Two routes, one per area, for the reason the language switcher gives: the session
    // driver is `database` and tenancy switches the connection, so a workspace posting to
    // the central route finds no session and CSRF rejects it as a 419.
    const url = tenant ? updateTenantColumns().url : updateAdminColumns().url;

    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // A save in flight when the page goes away is a save that never happened. Clearing on
    // unmount is what stops a pending timer firing against a stale url after navigation.
    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const change = useCallback(
        (next: ColumnLayout) => {
            setLayout(next);

            if (timer.current !== null) {
                clearTimeout(timer.current);
            }

            timer.current = setTimeout(() => {
                router.put(
                    url,
                    {
                        table: tableKey,
                        // Null rather than a copy of the declarations: "never touched"
                        // and "reset to default" are then the same stored state, and the
                        // column only ever holds what somebody actually changed.
                        layout: isDefaultLayout(next, columns) ? null : next,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        // The saved layout is not something this page reads back — it
                        // seeded the state once and the state has moved on since. Asking
                        // for no props keeps a checkbox from re-running the index query.
                        only: [],
                    },
                );
            }, SAVE_DELAY);
        },
        [url, tableKey, columns],
    );

    return [layout, change];
}
