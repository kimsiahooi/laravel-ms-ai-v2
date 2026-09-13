import { StockPickerField } from '@/components/form/stock-picker-field';
import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { AvailabilityPanel } from '@/pages/sales-orders/_components/availability-panel';
import { index as warehousesIndex } from '@/routes/warehouses';

/**
 * Everything a person reads and answers before confirming a despatch: what shipping means,
 * where from, and what that building holds.
 *
 * Split out of {@see OrderActions} when that file crossed the size signal — and the seam is
 * the honest one rather than the convenient one. **Nothing here holds state.** The chosen
 * warehouse, the in-flight flag and the refusal all stay with the component that owns the two
 * endings, because they are what the two endings share; what moved is the part that only
 * renders. So this takes the answer and reports a new one, and knows nothing about
 * confirming, cancelling, or which of them is running.
 */
export function FulfilFields({
    warehouses,
    chosenWarehouse,
    availability,
    refused,
    onChange,
}: {
    /** Where the goods may be shipped from. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /** Seeds the picker from `?warehouse_id` — see {@see OrderActions}. */
    chosenWarehouse: string;
    /** What the chosen warehouse holds, or null until one has been chosen. */
    availability: App.Data.StockAvailabilityData[] | null;
    /** The server's last refusal about this field, or undefined. */
    refused: string | undefined;
    onChange: (warehouseId: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <>
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
                        onChange={onChange}
                        error={refused}
                        placeholder="sales-orders.fulfil.warehouse_placeholder"
                        searchPlaceholder="sales-orders.fulfil.warehouse_search"
                        emptyMessage="sales-orders.fulfil.warehouse_empty"
                    />
                </div>
            )}

            {availability !== null && <AvailabilityPanel rows={availability} />}
        </>
    );
}
