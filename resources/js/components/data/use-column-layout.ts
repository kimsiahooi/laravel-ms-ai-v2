import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { ColumnLayout } from '@/components/data/column-layout';
import {
    defaultLayout,
    isDefaultLayout,
} from '@/components/data/column-layout';
import { useTranslation } from '@/hooks/use-translation';
import { update as updateAdminColumns } from '@/routes/admin/table-columns';
import { update as updateTenantColumns } from '@/routes/tenant/table-columns';

/** The part of a column definition this hook needs — see column-layout.ts. */
type Described = Parameters<typeof defaultLayout>[0][number];

/** How long to wait before saving, so dragging is one request rather than one per step. */
const SAVE_DELAY = 500;

/**
 * One toast, however many saves fail. A dropped connection means every change fails, and
 * a stack of identical complaints is worse than the silence it replaced.
 */
const FAILURE_TOAST = 'table-columns-save-failed';

/** Laravel's CSRF cookie, which it checks back as the `X-XSRF-TOKEN` header. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match?.[1] === undefined ? '' : decodeURIComponent(match[1]);
}

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
 * **Saved with `fetch`, deliberately, rather than through Inertia's router.** A background
 * save is not a navigation and should not join the page lifecycle. Going through the
 * router made every failure the page's problem: a 500 or an expired session put a
 * full-screen error overlay in front of somebody who had ticked a checkbox — saying
 * nothing about columns — and a dropped connection said nothing at all, while the column
 * stayed on screen looking saved until the next reload quietly took it away. A bare
 * request has one job and reports one outcome.
 *
 * **A failed save keeps the change and says so.** Reverting would be the other honest
 * option and it is the worse one: the arrangement still works for this session, which is
 * what was actually asked for, and taking it away because of an unrelated server fault
 * punishes the reader twice. The toast is what stops the screen asserting something false.
 *
 * Beside the table kit rather than in `hooks/`, because it is the table's own business and
 * has no second consumer.
 */
export function useColumnLayout(
    tableKey: App.Enums.TableKey,
    columns: readonly Described[],
): [ColumnLayout, (next: ColumnLayout) => void] {
    const { tableColumns, tenant } = usePage().props;
    const { t } = useTranslation();

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

    // A save still pending when the page goes away is a save that never happened. Clearing
    // on unmount stops a timer firing against a url the reader has already left.
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

            timer.current = setTimeout(async () => {
                try {
                    const response = await fetch(url, {
                        method: 'PUT',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-XSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify({
                            table: tableKey,
                            // Null rather than a copy of the declarations: "never touched"
                            // and "reset to default" are then the same stored state, and
                            // the column only holds what somebody actually changed.
                            layout: isDefaultLayout(next, columns)
                                ? null
                                : next,
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    // A save that worked replaces the complaint about the one that did
                    // not, so a recovered connection clears the warning it caused.
                    toast.dismiss(FAILURE_TOAST);
                } catch {
                    // Every failure shape lands here — a refused save, an expired session,
                    // a connection that dropped — and they all mean the same thing to the
                    // reader, so they get the same sentence.
                    toast.error(t('common.columns.save_failed'), {
                        id: FAILURE_TOAST,
                    });
                }
            }, SAVE_DELAY);
        },
        [url, tableKey, columns, t],
    );

    return [layout, change];
}
