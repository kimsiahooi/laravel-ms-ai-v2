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
 * **Both ends report what they hold, each under its own picker.** The first cut showed
 * only the source, on the reasoning that it is the only end that can refuse — which was
 * wrong about what the reader is doing. Deciding *how much* to move is a judgement about
 * both sides: you are usually levelling two shelves, and the destination's number is half
 * of that. Each line sits under the warehouse it describes, so neither has to say which
 * one it means.
 *
 * Advisory, both of them: the numbers are read without a lock and the real check happens
 * under one inside StockService, which is why nothing here disables anything.
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
    const [toWarehouseId, setToWarehouseId] = useState('');
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

                    {/* Each end reports what it holds, under the picker that names it —
                        so neither line has to say which warehouse it means. */}
                    <div className="space-y-2">
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

                        <OnHandLine warehouseId={fromWarehouseId} item={item} />
                    </div>

                    <div className="space-y-2">
                        <StockPickerField
                            name="to_warehouse_id"
                            label="stock-transfers.field.to"
                            entries={warehouseEntries}
                            placeholder="stock-transfers.field.to_placeholder"
                            searchPlaceholder="stock-transfers.field.warehouse_search"
                            emptyMessage="stock-transfers.field.warehouse_empty"
                            onChange={setToWarehouseId}
                            error={errors.to_warehouse_id}
                        />

                        <OnHandLine warehouseId={toWarehouseId} item={item} />
                    </div>

                    <TextField
                        name="quantity"
                        label="stock-transfers.field.quantity"
                        placeholder="stock-transfers.field.quantity_placeholder"
                        error={errors.quantity}
                        inputMode="decimal"
                    />

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
