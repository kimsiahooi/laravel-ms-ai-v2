import { StockPickerField } from '@/components/form/stock-picker-field';
import { InlineLink } from '@/components/inline-link';
import { useTranslation } from '@/hooks/use-translation';
import { index as warehousesIndex } from '@/routes/warehouses';

/**
 * Everything a person reads and answers before confirming that the goods are back: what
 * completing means, and which shelf they are going onto.
 *
 * **There is no availability panel here, and there never will be.** Its opposite number,
 * {@see CompleteFields} on a purchase return, carries one because sending goods back is a
 * subtraction and the shelf is allowed to say no. Taking them in is an addition: there is
 * nothing to check, so the only question on this card is where.
 *
 * Nothing here holds state — the chosen warehouse, the in-flight flag and the refusal all stay
 * with {@see CompletionCard}, which owns the two endings.
 */
export function CompleteFields({
    warehouses,
    chosenWarehouse,
    refused,
    onChange,
}: {
    /** Where the goods may be received into. Empty in a workspace with no warehouse yet. */
    warehouses: App.Data.WarehouseOptionData[];
    /** Seeds the picker — the despatch's own warehouse, unless it has since been removed. */
    chosenWarehouse: string;
    /** The server's last refusal about this field, or undefined. */
    refused: string | undefined;
    onChange: (warehouseId: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <>
            <div className="space-y-1">
                <h2 className="font-medium">
                    {t('sales-returns.complete.heading')}
                </h2>
                <p className="max-w-2xl text-muted-foreground text-sm">
                    {t('sales-returns.complete.description')}
                </p>
            </div>

            {warehouses.length === 0 ? (
                // Nowhere to receive into. Saying so beats a picker with no options and a
                // button that refuses to explain itself.
                <p className="text-sm">
                    {t('sales-returns.complete.no_warehouses')}{' '}
                    <InlineLink href={warehousesIndex()}>
                        {t('sales-returns.complete.no_warehouses_action')}
                    </InlineLink>
                </p>
            ) : (
                <div className="max-w-sm">
                    {/* Two lines per row, so two sites with a "Main store" stay tellable
                        apart — the reason this picker exists rather than ComboboxField. */}
                    <StockPickerField
                        name="warehouse_id"
                        label="sales-returns.complete.warehouse"
                        entries={warehouses.map((warehouse) => ({
                            value: String(warehouse.id),
                            primary: warehouse.name,
                            secondary: warehouse.site,
                        }))}
                        defaultValue={chosenWarehouse}
                        onChange={onChange}
                        error={refused}
                        placeholder="sales-returns.complete.warehouse_placeholder"
                        searchPlaceholder="sales-returns.complete.warehouse_search"
                        emptyMessage="sales-returns.complete.warehouse_empty"
                    />
                </div>
            )}
        </>
    );
}
