import { useMemo } from 'react';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { stockTakeLineSchema } from '@/lib/validation/schemas/stock-take-line';
import { lines } from '@/routes/stock-takes';

/**
 * Putting something on the sheet that the warehouse does not think it has.
 *
 * This is the found box on the back shelf — the thing a physical count exists to
 * discover, and the thing v1 had no way to record. The line joins at zero expected, so
 * whatever is counted into it becomes the whole difference; nothing is guessed on the
 * counter's behalf.
 *
 * `StockPickerField` comes from `components/form/`, which is where it was promoted when
 * transfers became its second consumer — stock takes are the third it was promised to.
 * Copying it into this module's `_components/` would be a fourth copy of a picker that
 * addresses two tables through one value, and `check:structure` refuses the reach across
 * anyway.
 *
 * **Grouped, like movements and transfers.** Products and raw materials answer two
 * different questions somebody has while standing at a shelf, and nineteen names in one
 * flat list is a scroll rather than a choice. The headings are this module's own words
 * rather than borrowed ones, so a rewording here cannot reach into the ledger's picker.
 */
export function AddItemDialog({
    takeId,
    options,
    open,
    onOpenChange,
}: {
    takeId: number;
    options: App.Data.StockItemOptionData[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const entries: StockPickerEntry[] = useMemo(
        () =>
            options.map((option) => ({
                value: option.value,
                primary: option.name,
                secondary: option.sku,
                group:
                    option.type === 'product'
                        ? 'stock-takes.field.item_group_product'
                        : 'stock-takes.field.item_group_raw_material',
            })),
        [options],
    );

    // The browser can only refuse an item it was told does not exist, so the schema is
    // a function of what this screen actually offered. The duplicate is the server's
    // call — it is the only side that knows what is already on the sheet.
    const schema = useMemo(
        () => stockTakeLineSchema(options.map((option) => option.value)),
        [options],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={lines.form({ stockTake: takeId })}
            schema={schema}
            title="stock-takes.dialog.add_item.title"
            description="stock-takes.dialog.add_item.description"
            submit="stock-takes.dialog.add_item.submit"
            submitting="stock-takes.dialog.add_item.submitting"
        >
            {({ errors }) => (
                <StockPickerField
                    name="item"
                    label="stock-takes.field.item"
                    entries={entries}
                    placeholder="stock-takes.field.item_placeholder"
                    searchPlaceholder="stock-takes.field.item_search"
                    emptyMessage="stock-takes.field.item_empty"
                    error={errors.item}
                />
            )}
        </ResourceFormDialog>
    );
}
