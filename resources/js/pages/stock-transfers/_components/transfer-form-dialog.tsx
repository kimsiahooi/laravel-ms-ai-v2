import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { OnHandLine } from '@/components/form/on-hand-line';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { TextField } from '@/components/form/text-field';
import { stockTransferSchema } from '@/lib/validation/schemas/stock-transfer';
import { store } from '@/routes/stock-transfers';

type PageProps = {
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
};

/**
 * Moving stock between two warehouses.
 *
 * **Item first, then the two ends.** A transfer is decided in that order — you know
 * what you are moving before you know where it is going — and it is also the order that
 * makes the on-hand line useful, since it can only speak once the item and the source
 * are both known.
 *
 * The on-hand line sits under the quantity and reports the **source**, which is the
 * only end that can refuse. It is advisory: the number was read without a lock and the
 * real check happens under one, inside StockService. Showing the destination's level
 * too would be answering a question nobody asked while implying the same authority.
 *
 * There is no edit form. A transfer is a record of something that happened.
 */
export function TransferFormDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { warehouses, items } = usePage<PageProps>().props;

    // Mirrored here only so the on-hand line can see the source and the item together.
    // Each picker still owns its value and its hidden input — a second reader, not a
    // second source of truth.
    const [fromWarehouseId, setFromWarehouseId] = useState('');
    const [item, setItem] = useState('');

    const warehouseEntries: StockPickerEntry[] = useMemo(
        () =>
            warehouses.map((warehouse) => ({
                value: String(warehouse.id),
                primary: warehouse.name,
                secondary: warehouse.site,
            })),
        [warehouses],
    );

    const itemEntries: StockPickerEntry[] = useMemo(
        () =>
            items.map((each) => ({
                value: each.value,
                primary: each.name,
                secondary: each.sku,
                group:
                    each.type === 'product'
                        ? 'stock-transfers.field.item_group_product'
                        : 'stock-transfers.field.item_group_raw_material',
            })),
        [items],
    );

    const schema = useMemo(
        () =>
            stockTransferSchema(
                items.map((each) => each.value),
                warehouses.map((warehouse) => warehouse.id),
            ),
        [items, warehouses],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={store.form()}
            schema={schema}
            title="stock-transfers.create.title"
            description="stock-transfers.create.description"
            submit="stock-transfers.create.submit"
            submitting="stock-transfers.create.submitting"
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <StockPickerField
                        name="item"
                        label="stock-transfers.field.item"
                        entries={itemEntries}
                        placeholder="stock-transfers.field.item_placeholder"
                        searchPlaceholder="stock-transfers.field.item_search"
                        emptyMessage="stock-transfers.field.item_empty"
                        onChange={setItem}
                        error={errors.item}
                    />

                    <StockPickerField
                        name="from_warehouse_id"
                        label="stock-transfers.field.from"
                        entries={warehouseEntries}
                        placeholder="stock-transfers.field.from_placeholder"
                        searchPlaceholder="stock-transfers.field.warehouse_search"
                        emptyMessage="stock-transfers.field.warehouse_empty"
                        onChange={setFromWarehouseId}
                        error={errors.from_warehouse_id}
                    />

                    <StockPickerField
                        name="to_warehouse_id"
                        label="stock-transfers.field.to"
                        entries={warehouseEntries}
                        placeholder="stock-transfers.field.to_placeholder"
                        searchPlaceholder="stock-transfers.field.warehouse_search"
                        emptyMessage="stock-transfers.field.warehouse_empty"
                        error={errors.to_warehouse_id}
                    />

                    <div className="space-y-2">
                        <TextField
                            name="quantity"
                            label="stock-transfers.field.quantity"
                            placeholder="stock-transfers.field.quantity_placeholder"
                            error={errors.quantity}
                            inputMode="decimal"
                        />

                        {/* The source's level, beside the box it constrains. */}
                        <OnHandLine warehouseId={fromWarehouseId} item={item} />
                    </div>

                    <TextField
                        name="notes"
                        label="stock-transfers.field.notes"
                        placeholder="stock-transfers.field.notes_placeholder"
                        error={errors.notes}
                        optional
                        rows={2}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
