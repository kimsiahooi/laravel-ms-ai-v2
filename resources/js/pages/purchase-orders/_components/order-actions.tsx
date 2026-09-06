import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { InlineLink } from '@/components/inline-link';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { cancel, receive } from '@/routes/purchase-orders';
import { index as warehousesIndex } from '@/routes/warehouses';

type Order = App.Data.PurchaseOrderData;

/** The two ways a pending order ends. Both are one-way, and both ask first. */
type Ending = 'receive' | 'cancel';

/**
 * The end of the order: take the delivery in, or call it off.
 *
 * **Both endings live in one component, which is the point of it.** They share a single
 * in-flight flag, so while either request is running neither button can be pressed —
 * receiving an order while its cancellation is in flight is a race anybody wins by being
 * impatient, and two components would have to lift that state into the page, which would
 * then be the only place the rule was written down. The same shape `PostTakeDialog` has.
 *
 * **The warehouse is chosen out here, not inside the dialog.** Receiving needs an answer
 * before it can be confirmed at all, and {@see ConfirmDialog} deliberately takes no
 * fields — it states a consequence and asks. So the picker sits on the page where it can
 * be considered, and the dialog repeats the warehouse back in its own words, which is
 * where the last chance to notice the wrong one is.
 *
 * **Nothing here is undoable and both dialogs say so.** Receiving writes a stock movement
 * per line under `StockService`; cancelling closes a document somebody may have sent to a
 * supplier. Neither is a click to be recovered from.
 *
 * Renders nothing on an order that has already ended, and nothing without the permission
 * — the hooks run first regardless, because a conditional hook is a different component
 * on the next render.
 */
export function OrderActions({
    order,
    warehouses,
}: {
    order: Order;
    /** Where a delivery may be booked in. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [warehouseId, setWarehouseId] = useState('');
    const [confirming, setConfirming] = useState<Ending | null>(null);
    const [busy, setBusy] = useState<Ending | null>(null);
    // Held here rather than read off the page, because this is not a `<Form>` and there
    // is no field bag for the picker to look itself up in. The one failure that reaches
    // it is `warehouse_id` — a warehouse archived in another tab since this page
    // rendered, or the short-stock message the receive Action declares.
    const [refused, setRefused] = useState<string | undefined>(undefined);

    const send = (
        ending: Ending,
        url: string,
        data: Record<string, string>,
    ) => {
        router.post(url, data, {
            preserveScroll: true,
            // onStart/onFinish rather than onSuccess: a refused request has to release
            // the buttons instead of leaving the document frozen behind a spinner.
            onStart: () => {
                setBusy(ending);
                setRefused(undefined);
            },
            onError: (bag) => setRefused(bag.warehouse_id),
            onFinish: () => {
                setBusy(null);
                // The dialog closes either way. A message about the warehouse belongs
                // beside the picker, which is on the page behind it.
                setConfirming(null);
            },
        });
    };

    if (!can('purchase-orders.update') || order.status !== 'pending') {
        return null;
    }

    // Either request holds both buttons. See above — this is the asymmetry being fixed.
    const working = busy !== null;
    const chosen = warehouses.find(
        (warehouse) => String(warehouse.id) === warehouseId,
    );

    return (
        <Card>
            <CardContent className="space-y-4">
                <div className="space-y-1">
                    <h2 className="font-medium">
                        {t('purchase-orders.receive.heading')}
                    </h2>
                    <p className="max-w-2xl text-muted-foreground text-sm">
                        {t('purchase-orders.receive.description')}
                    </p>
                </div>

                {warehouses.length === 0 ? (
                    // Nothing to receive into. Saying so beats a picker with no options
                    // and a button that refuses to explain itself.
                    <p className="text-sm">
                        {t('purchase-orders.receive.no_warehouses')}{' '}
                        <InlineLink href={warehousesIndex()}>
                            {t('purchase-orders.receive.no_warehouses_action')}
                        </InlineLink>
                    </p>
                ) : (
                    <div className="max-w-sm">
                        {/* Two lines per row, so two sites with a "Main store" stay
                            tellable apart — the reason this picker exists rather than
                            ComboboxField. */}
                        <StockPickerField
                            name="warehouse_id"
                            label="purchase-orders.receive.warehouse"
                            entries={warehouses.map((warehouse) => ({
                                value: String(warehouse.id),
                                primary: warehouse.name,
                                secondary: warehouse.site,
                            }))}
                            onChange={setWarehouseId}
                            error={refused}
                            placeholder="purchase-orders.receive.warehouse_placeholder"
                            searchPlaceholder="purchase-orders.receive.warehouse_search"
                            emptyMessage="purchase-orders.receive.warehouse_empty"
                        />
                    </div>
                )}

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        disabled={working}
                        onClick={() => setConfirming('cancel')}
                    >
                        {t('purchase-orders.action.cancel')}
                    </Button>
                    <Button
                        disabled={working || chosen === undefined}
                        onClick={() => setConfirming('receive')}
                    >
                        {t('purchase-orders.action.receive')}
                    </Button>
                </div>
            </CardContent>

            <ConfirmDialog
                open={confirming === 'receive'}
                onOpenChange={(open) => setConfirming(open ? 'receive' : null)}
                title={t('purchase-orders.dialog.receive.title')}
                // The warehouse is named again here because it is the one thing that
                // cannot be corrected afterwards — the stock is in that building.
                description={t('purchase-orders.dialog.receive.description', {
                    warehouse: chosen?.name ?? '',
                    lines: order.line_count,
                })}
                confirmLabel={t('purchase-orders.dialog.receive.submit')}
                busyLabel={t('purchase-orders.dialog.receive.submitting')}
                processing={busy === 'receive'}
                onConfirm={() =>
                    send('receive', receive({ purchaseOrder: order.id }).url, {
                        warehouse_id: warehouseId,
                    })
                }
            />

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={t('purchase-orders.dialog.cancel.title')}
                description={t('purchase-orders.dialog.cancel.description')}
                confirmLabel={t('purchase-orders.dialog.cancel.submit')}
                busyLabel={t('purchase-orders.dialog.cancel.submitting')}
                // Destructive, unlike receiving: this one closes the order for good.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ purchaseOrder: order.id }).url, {})
                }
            />
        </Card>
    );
}
