import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CompleteFields } from '@/pages/purchase-returns/_components/complete-fields';
import { cancel, complete } from '@/routes/purchase-returns';

type Return = App.Data.PurchaseReturnData;

/** The two ways a pending return ends. Both are one-way, and both ask first. */
type Ending = 'complete' | 'cancel';

/**
 * The end of the return: send the goods back, or call it off.
 *
 * The mirror of {@see OrderActions} on a sales order, and every reason that file gives applies
 * here unchanged. **Both endings live in one component**, so they share a single in-flight flag
 * and neither button can be pressed while the other is running. **The warehouse is chosen out
 * here, not inside the dialog**, because {@see ConfirmDialog} deliberately takes no fields — it
 * states a consequence and asks — so the picker sits on the page where it can be considered,
 * with the availability panel under it, and the dialog repeats the warehouse back in its own
 * words. **Choosing one fetches what it holds** through `router.reload({ only: ['availability']
 * })`, which also puts the warehouse in the URL, which is what lets a refused completion come
 * back to a page that still shows why. **The panel never disables the button**: its numbers are
 * read without a lock and are stale on arrival, so showing three must not stop somebody sending
 * back four that a colleague's delivery has just made possible.
 *
 * **Where it differs from the sales-order card is the picker's starting value.** A sales order
 * carries no warehouse until it ships, so its picker opens empty. A return knows where the
 * delivery landed, so the server seeds `chosenWarehouse` with the order's received warehouse and
 * the panel is populated on first paint — without pinning it, because stock gets transferred
 * between sites and the goods may not be in that building any more.
 *
 * **The other difference is what a refusal can mean.** A shortfall arrives on `warehouse_id` and
 * lands under the picker, where changing the answer might fix it. An over-return — another
 * return completed first — arrives as a toast instead, because no warehouse fixes it and the
 * only way out is to edit this return down.
 *
 * Renders nothing on a return that has already ended, and nothing without the permission — the
 * hooks run first regardless, because a conditional hook is a different component on the next
 * render.
 */
export function CompletionCard({
    row,
    warehouses,
    chosenWarehouse,
    availability,
}: {
    row: Return;
    /** Where the goods may be sent from. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /**
     * The warehouse `availability` was computed against, or `''`.
     *
     * `?warehouse_id` when the URL names one, and otherwise the delivery's own warehouse — see
     * the controller. The picker is seeded from it, so a page opened straight at
     * `?warehouse_id=2` shows the building its own panel is about.
     */
    chosenWarehouse: string;
    /** What the chosen warehouse holds, or null when there is nowhere to send from. */
    availability: App.Data.StockAvailabilityData[] | null;
}) {
    const { t, tChoice } = useTranslation();
    const { can } = usePermissions();
    // Seeded once from the server's answer and owned here after that. Not synchronised with the
    // prop on every render: from the first click onwards the picker is the authority and the URL
    // is only its echo.
    const [warehouseId, setWarehouseId] = useState(chosenWarehouse);
    const [confirming, setConfirming] = useState<Ending | null>(null);
    const [busy, setBusy] = useState<Ending | null>(null);
    // Held here rather than read off the page, because this is not a `<Form>` and there is no
    // field bag for the picker to look itself up in. The failures that reach it are a warehouse
    // archived in another tab since this page rendered, and the shortfall the completion Action
    // declares.
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

    const chooseWarehouse = (chosenId: string) => {
        setWarehouseId(chosenId);
        setRefused(undefined);

        // The answer lands in `availability`, and the warehouse lands in the URL — the second
        // half matters as much as the first, because a refused completion redirects here and
        // re-renders in full. `replace` is what stops each change piling up a history entry
        // somebody has to press Back through.
        router.reload({
            only: ['availability'],
            data: { warehouse_id: chosenId },
            replace: true,
        });
    };

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

    if (!can('purchase-returns.update') || row.status !== 'pending') {
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
                    availability={availability}
                    refused={refused}
                    onChange={chooseWarehouse}
                />

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        disabled={working}
                        onClick={() => setConfirming('cancel')}
                    >
                        {t('purchase-returns.action.cancel')}
                    </Button>
                    <Button
                        // Disabled for want of an *answer*, never for want of stock. See above.
                        disabled={working || chosen === undefined}
                        onClick={() => setConfirming('complete')}
                    >
                        {t('purchase-returns.action.complete')}
                    </Button>
                </div>
            </CardContent>

            <ConfirmDialog
                open={confirming === 'complete'}
                onOpenChange={(open) => setConfirming(open ? 'complete' : null)}
                title={t('purchase-returns.dialog.complete.title')}
                // The warehouse is named again here because it is the one thing that cannot be
                // corrected afterwards — the stock has left that building.
                // `tChoice`, not `t`: "All 1 lines" is what a single-line return reads as
                // otherwise, and a `count === 1 ? a : b` at this call site would be wrong in two
                // of the three languages we ship.
                description={tChoice(
                    'purchase-returns.dialog.complete.description',
                    row.line_count,
                    {
                        warehouse: chosen?.name ?? '',
                        count: row.line_count,
                    },
                )}
                confirmLabel={t('purchase-returns.dialog.complete.submit')}
                busyLabel={t('purchase-returns.dialog.complete.submitting')}
                processing={busy === 'complete'}
                onConfirm={() =>
                    send('complete', complete({ purchaseReturn: row.id }).url, {
                        warehouse_id: warehouseId,
                    })
                }
            />

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={t('purchase-returns.dialog.cancel.title')}
                description={t('purchase-returns.dialog.cancel.description')}
                confirmLabel={t('purchase-returns.dialog.cancel.submit')}
                busyLabel={t('purchase-returns.dialog.cancel.submitting')}
                // Destructive, unlike completing: this one closes the return for good, and
                // nothing is gained by it.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ purchaseReturn: row.id }).url, {})
                }
            />
        </Card>
    );
}
