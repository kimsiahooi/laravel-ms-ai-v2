import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { InlineLink } from '@/components/inline-link';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { AvailabilityPanel } from '@/pages/sales-orders/_components/availability-panel';
import { cancel, fulfill } from '@/routes/sales-orders';
import { index as warehousesIndex } from '@/routes/warehouses';

type Order = App.Data.SalesOrderData;

/** The two ways a pending order ends. Both are one-way, and both ask first. */
type Ending = 'fulfill' | 'cancel';

/**
 * The end of the order: ship the goods, or call it off.
 *
 * **Both endings live in one component, which is the point of it.** They share a single
 * in-flight flag, so while either request is running neither button can be pressed — shipping
 * an order while its cancellation is in flight is a race anybody wins by being impatient, and
 * two components would have to lift that state into the page, which would then be the only
 * place the rule was written down.
 *
 * **The warehouse is chosen out here, not inside the dialog.** Shipping needs an answer before
 * it can be confirmed at all, and {@see ConfirmDialog} deliberately takes no fields — it states
 * a consequence and asks. So the picker sits on the page where it can be considered, with the
 * availability panel under it, and the dialog repeats the warehouse back in its own words,
 * which is where the last chance to notice the wrong one is.
 *
 * **The picker is seeded from the URL, not from empty.** `?warehouse_id` is what decides
 * whether there is a panel at all, so a page opened straight at that URL has to show which
 * building the figures belong to — otherwise the panel is about a warehouse nothing names and
 * the Fulfil button is disabled with no visible reason.
 *
 * **Choosing a warehouse fetches what it holds, and that is a server round trip on purpose.**
 * Every level for every product could travel with the page, but a figure baked in at page load
 * goes quietly stale while somebody reads the document. `router.reload` asks for the one prop
 * — `only: ['availability']` — and puts the chosen warehouse in the URL, which is what lets a
 * refused despatch come back to a page that still shows why. `reload` preserves scroll and
 * component state by definition, so the picker stays on the choice that was made.
 *
 * **The panel never disables the button.** Its numbers are read without a lock and are stale on
 * arrival — see the panel — so showing three must not stop somebody shipping four that a
 * colleague's delivery has just made possible. The server refuses, and says which product.
 *
 * **Nothing here is undoable and both dialogs say so.** Fulfilling writes a stock movement per
 * line under `StockService`; cancelling closes a document a customer may already have been
 * sent.
 *
 * Renders nothing on an order that has already ended, and nothing without the permission — the
 * hooks run first regardless, because a conditional hook is a different component on the next
 * render.
 */
export function OrderActions({
    order,
    warehouses,
    chosenWarehouse,
    availability,
}: {
    order: Order;
    /** Where the goods may be shipped from. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /**
     * The warehouse the URL named and `availability` was computed against, or `''`.
     *
     * The picker is seeded from it, so a page opened straight at `?warehouse_id=2` — a
     * refresh, a shared link — shows the building its own panel is about.
     */
    chosenWarehouse: string;
    /** What the chosen warehouse holds, or null until one has been chosen. */
    availability: App.Data.StockAvailabilityData[] | null;
}) {
    const { t, tChoice } = useTranslation();
    const { can } = usePermissions();
    // Seeded once from the server's answer and owned here after that. Not synchronised with
    // the prop on every render: from the first click onwards the picker is the authority and
    // the URL is only its echo.
    const [warehouseId, setWarehouseId] = useState(chosenWarehouse);
    const [confirming, setConfirming] = useState<Ending | null>(null);
    const [busy, setBusy] = useState<Ending | null>(null);
    // Held here rather than read off the page, because this is not a `<Form>` and there is no
    // field bag for the picker to look itself up in. The failures that reach it are a
    // warehouse archived in another tab since this page rendered, and the shortfall the
    // fulfil Action declares.
    const [refused, setRefused] = useState<string | undefined>(undefined);

    const chooseWarehouse = (chosenId: string) => {
        setWarehouseId(chosenId);
        setRefused(undefined);

        // The answer lands in `availability`, and the warehouse lands in the URL — see the
        // component note on why the second half matters as much as the first. Scroll and
        // component state are not named: `reload` forces both and `ReloadOptions` omits them
        // from the type, which is the API saying it has already decided. `replace` is what
        // stops each change piling up a history entry somebody has to press Back through.
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

    if (!can('sales-orders.update') || order.status !== 'pending') {
        return null;
    }

    // Either request holds both buttons. See above — this is the race being closed.
    const working = busy !== null;
    const chosen = warehouses.find(
        (warehouse) => String(warehouse.id) === warehouseId,
    );

    return (
        <Card>
            <CardContent className="space-y-4">
                <div className="space-y-1">
                    <h2 className="font-medium">
                        {t('sales-orders.fulfil.heading')}
                    </h2>
                    <p className="max-w-2xl text-muted-foreground text-sm">
                        {t('sales-orders.fulfil.description')}
                    </p>
                </div>

                {warehouses.length === 0 ? (
                    // Nowhere to ship from. Saying so beats a picker with no options and a
                    // button that refuses to explain itself.
                    <p className="text-sm">
                        {t('sales-orders.fulfil.no_warehouses')}{' '}
                        <InlineLink href={warehousesIndex()}>
                            {t('sales-orders.fulfil.no_warehouses_action')}
                        </InlineLink>
                    </p>
                ) : (
                    <div className="max-w-sm">
                        {/* Two lines per row, so two sites with a "Main store" stay tellable
                            apart — the reason this picker exists rather than ComboboxField. */}
                        <StockPickerField
                            name="warehouse_id"
                            label="sales-orders.fulfil.warehouse"
                            entries={warehouses.map((warehouse) => ({
                                value: String(warehouse.id),
                                primary: warehouse.name,
                                secondary: warehouse.site,
                            }))}
                            defaultValue={chosenWarehouse}
                            onChange={chooseWarehouse}
                            error={refused}
                            placeholder="sales-orders.fulfil.warehouse_placeholder"
                            searchPlaceholder="sales-orders.fulfil.warehouse_search"
                            emptyMessage="sales-orders.fulfil.warehouse_empty"
                        />
                    </div>
                )}

                {availability !== null && (
                    <AvailabilityPanel rows={availability} />
                )}

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        disabled={working}
                        onClick={() => setConfirming('cancel')}
                    >
                        {t('sales-orders.action.cancel')}
                    </Button>
                    <Button
                        // Disabled for want of an *answer*, never for want of stock. See above.
                        disabled={working || chosen === undefined}
                        onClick={() => setConfirming('fulfill')}
                    >
                        {t('sales-orders.action.fulfil')}
                    </Button>
                </div>
            </CardContent>

            <ConfirmDialog
                open={confirming === 'fulfill'}
                onOpenChange={(open) => setConfirming(open ? 'fulfill' : null)}
                title={t('sales-orders.dialog.fulfil.title')}
                // The warehouse is named again here because it is the one thing that cannot be
                // corrected afterwards — the stock has left that building.
                // `tChoice`, not `t`: "All 1 lines" is what a single-line order reads as
                // otherwise, and a `count === 1 ? a : b` at this call site would be wrong in
                // two of the three languages we ship.
                description={tChoice(
                    'sales-orders.dialog.fulfil.description',
                    order.line_count,
                    {
                        warehouse: chosen?.name ?? '',
                        count: order.line_count,
                    },
                )}
                confirmLabel={t('sales-orders.dialog.fulfil.submit')}
                busyLabel={t('sales-orders.dialog.fulfil.submitting')}
                processing={busy === 'fulfill'}
                onConfirm={() =>
                    send('fulfill', fulfill({ salesOrder: order.id }).url, {
                        warehouse_id: warehouseId,
                    })
                }
            />

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={t('sales-orders.dialog.cancel.title')}
                description={t('sales-orders.dialog.cancel.description')}
                confirmLabel={t('sales-orders.dialog.cancel.submit')}
                busyLabel={t('sales-orders.dialog.cancel.submitting')}
                // Destructive, unlike fulfilling: this one closes the order for good.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ salesOrder: order.id }).url, {})
                }
            />
        </Card>
    );
}
