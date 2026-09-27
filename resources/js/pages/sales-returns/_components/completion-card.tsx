import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CompleteFields } from '@/pages/sales-returns/_components/complete-fields';
import { cancel, complete } from '@/routes/sales-returns';

type Return = App.Data.SalesReturnData;

/** The two ways a pending return ends. Both are one-way, and both ask first. */
type Ending = 'complete' | 'cancel';

/**
 * The end of the return: take the goods back in, or call it off.
 *
 * The mirror of the purchase side's card, and the shared reasons hold. **Both endings live in
 * one component**, so they share a single in-flight flag and neither button can be pressed while
 * the other is running. **The warehouse is chosen out here, not inside the dialog**, because
 * {@see ConfirmDialog} deliberately takes no fields — it states a consequence and asks — so the
 * picker sits on the page where it can be considered, and the dialog repeats the warehouse back
 * in its own words.
 *
 * **Two things the purchase card does are deliberately absent here, and both follow from the
 * direction.** Taking stock in cannot run short, so there is no availability panel to read — and
 * with nothing to fetch, choosing a warehouse is **not a server round trip**: no
 * `router.reload`, no `?warehouse_id` in the URL, just local state that the form posts. The
 * purchase side needs the query string because its panel has to survive a full re-render after a
 * shortfall; there is no shortfall on this side to survive.
 *
 * **The picker opens on the despatch's own warehouse** rather than empty, because that is right
 * nearly every time — without being pinned to it, since returned goods routinely go back to a
 * different shelf.
 *
 * **The one refusal that can still arrive is the ceiling** — another return completed first — and
 * it comes as a toast rather than a field error, because no warehouse fixes it and the only way
 * out is to edit this return down.
 *
 * Renders nothing on a return that has already ended, and nothing without the permission — the
 * hooks run first regardless, because a conditional hook is a different component on the next
 * render.
 */
export function CompletionCard({
    row,
    warehouses,
    chosenWarehouse,
}: {
    row: Return;
    /** Where the goods may be received into. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /** The despatch's own warehouse, or `''` when it has none the picker still offers. */
    chosenWarehouse: string;
}) {
    const { t, tChoice } = useTranslation();
    const { can } = usePermissions();
    // Seeded once from the server's answer and owned here after that. Unlike the purchase side
    // there is no URL echoing it back, because nothing is fetched when it changes.
    const [warehouseId, setWarehouseId] = useState(chosenWarehouse);
    const [confirming, setConfirming] = useState<Ending | null>(null);
    const [busy, setBusy] = useState<Ending | null>(null);
    // Held here rather than read off the page, because this is not a `<Form>` and there is no
    // field bag for the picker to look itself up in. The one failure that reaches it is a
    // warehouse archived in another tab since this page rendered.
    const [refused, setRefused] = useState<string | undefined>(undefined);

    // A message the server wrote in the language of the request that produced it. When somebody
    // switches language it is suddenly the wrong language, and it would sit there until the next
    // request — so it is dropped the moment the locale moves. State that tracks a prop, adjusted
    // during render: it runs only when the locale actually changed, so it cannot interrupt
    // anything.
    const { locale } = usePage().props;
    const [seenLocale, setSeenLocale] = useState(locale);

    if (seenLocale !== locale) {
        setSeenLocale(locale);
        setRefused(undefined);
    }

    const send = (
        ending: Ending,
        url: string,
        data: Record<string, string>,
    ) => {
        router.post(url, data, {
            preserveScroll: true,
            // onStart/onFinish rather than onSuccess: a refused request has to release the
            // buttons instead of leaving the document frozen behind a spinner.
            onStart: () => {
                setBusy(ending);
                setRefused(undefined);
            },
            onError: (bag) => setRefused(bag.warehouse_id),
            onFinish: () => {
                setBusy(null);
                // The dialog closes either way. A message about the warehouse belongs beside
                // the picker, which is on the page behind it.
                setConfirming(null);
            },
        });
    };

    if (!can('sales-returns.update') || row.status !== 'pending') {
        return null;
    }

    // Either request holds both buttons — a return completed while its cancellation is in flight
    // is a race anybody wins by being impatient.
    const working = busy !== null;
    const chosen = warehouses.find(
        (warehouse) => String(warehouse.id) === warehouseId,
    );

    return (
        <Card>
            <CardContent className="space-y-4">
                <CompleteFields
                    warehouses={warehouses}
                    chosenWarehouse={chosenWarehouse}
                    refused={refused}
                    onChange={(chosenId) => {
                        setWarehouseId(chosenId);
                        setRefused(undefined);
                    }}
                />

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        disabled={working}
                        onClick={() => setConfirming('cancel')}
                    >
                        {t('sales-returns.action.cancel')}
                    </Button>
                    <Button
                        // Disabled for want of an *answer*, and that is the only reason there
                        // is on this side.
                        disabled={working || chosen === undefined}
                        onClick={() => setConfirming('complete')}
                    >
                        {t('sales-returns.action.complete')}
                    </Button>
                </div>
            </CardContent>

            <ConfirmDialog
                open={confirming === 'complete'}
                onOpenChange={(open) => setConfirming(open ? 'complete' : null)}
                title={t('sales-returns.dialog.complete.title')}
                // The warehouse is named again here because it is the one thing that cannot be
                // corrected afterwards — the stock is on that shelf.
                // `tChoice`, not `t`: "All 1 lines" is what a single-line return reads as
                // otherwise, and a `count === 1 ? a : b` at this call site would be wrong in two
                // of the three languages we ship.
                description={tChoice(
                    'sales-returns.dialog.complete.description',
                    row.line_count,
                    {
                        warehouse: chosen?.name ?? '',
                        count: row.line_count,
                    },
                )}
                confirmLabel={t('sales-returns.dialog.complete.submit')}
                busyLabel={t('sales-returns.dialog.complete.submitting')}
                processing={busy === 'complete'}
                onConfirm={() =>
                    send('complete', complete({ salesReturn: row.id }).url, {
                        warehouse_id: warehouseId,
                    })
                }
            />

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={t('sales-returns.dialog.cancel.title')}
                description={t('sales-returns.dialog.cancel.description')}
                confirmLabel={t('sales-returns.dialog.cancel.submit')}
                busyLabel={t('sales-returns.dialog.cancel.submitting')}
                // Destructive, unlike completing: this one closes the return for good, and
                // nothing is gained by it.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ salesReturn: row.id }).url, {})
                }
            />
        </Card>
    );
}
