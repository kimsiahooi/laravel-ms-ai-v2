import { AvailabilityPanel } from '@/components/data/availability-panel';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { index as warehousesIndex } from '@/routes/warehouses';

/**
 * Everything a person reads and answers before confirming that the goods are going back: what
 * completing means, where they are leaving from, and what that building actually holds.
 *
 * The mirror of {@see FulfilFields}, and the seam is drawn in the same place: **nothing here
 * holds state.** The chosen warehouse, the in-flight flag and the refusal all stay with
 * {@see CompletionCard}, which owns the two endings, because they are what the two endings
 * share. This takes the answer and reports a new one, and knows nothing about completing,
 * cancelling, or which of them is running.
 */
export function CompleteFields({
    warehouses,
    chosenWarehouse,
    availability,
    refused,
    onChange,
}: {
    /** Where the goods may be sent from. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /** Seeds the picker — see {@see CompletionCard} on where the value comes from. */
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
                    {t('purchase-returns.complete.heading')}
                </h2>
                <p className="max-w-2xl text-muted-foreground text-sm">
                    {t('purchase-returns.complete.description')}
                </p>
            </div>

            {warehouses.length === 0 ? (
                // Nowhere to send from. Saying so beats a picker with no options and a
                // button that refuses to explain itself.
                <p className="text-sm">
                    {t('purchase-returns.complete.no_warehouses')}{' '}
                    <InlineLink href={warehousesIndex()}>
                        {t('purchase-returns.complete.no_warehouses_action')}
                    </InlineLink>
                </p>
            ) : (
                <div className="max-w-sm">
                    {/* Two lines per row, so two sites with a "Main store" stay tellable
                        apart — the reason this picker exists rather than ComboboxField. */}
                    <StockPickerField
                        name="warehouse_id"
                        label="purchase-returns.complete.warehouse"
                        entries={warehouses.map((warehouse) => ({
                            value: String(warehouse.id),
                            primary: warehouse.name,
                            secondary: warehouse.site,
                        }))}
                        defaultValue={chosenWarehouse}
                        onChange={onChange}
                        error={refused}
                        placeholder="purchase-returns.complete.warehouse_placeholder"
                        searchPlaceholder="purchase-returns.complete.warehouse_search"
                        emptyMessage="purchase-returns.complete.warehouse_empty"
                    />
                </div>
            )}

            {availability !== null && <AvailabilityPanel rows={availability} />}
        </>
    );
}
