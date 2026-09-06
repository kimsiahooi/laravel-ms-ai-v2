import { usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { OnHandLine } from '@/components/form/on-hand-line';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { TextField } from '@/components/form/text-field';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import type { MovementType } from '@/lib/validation/schemas/stock-movement';
import {
    MOVEMENT_TYPES,
    stockMovementSchema,
} from '@/lib/validation/schemas/stock-movement';
import { store } from '@/routes/stock-movements';
import type { TranslationKey } from '@/types/lang';

type PageProps = {
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
};

/** The words for each type: the tab, and the line explaining what it will do. */
const TYPE_COPY: Record<
    MovementType,
    { tab: TranslationKey; hint: TranslationKey }
> = {
    in: {
        tab: 'stock-movements.field.type_in',
        hint: 'stock-movements.field.type_hint_in',
    },
    out: {
        tab: 'stock-movements.field.type_out',
        hint: 'stock-movements.field.type_hint_out',
    },
    set: {
        tab: 'stock-movements.field.type_set',
        hint: 'stock-movements.field.type_hint_set',
    },
};

/**
 * Recording one movement.
 *
 * **The type is a segmented control, not a dropdown.** There are exactly three, they are
 * the first decision, and two of them are opposites — a closed control that has to be
 * opened before it will admit it offers "out" is a worse way to say that. It also changes what
 * the box below it means, and a choice with consequences should be visible while the
 * consequence is being typed.
 *
 * That is also why the schema is rebuilt when the type changes: `in` and `out` refuse
 * zero, because moving nothing appends a row to an append-only ledger saying nothing
 * happened. `set` allows it, because "the shelf is empty" is a real thing to record.
 * The server draws the same line.
 *
 * There is no edit form anywhere in this module. The ledger is append-only.
 */
export function MovementFormDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const { warehouses, items } = usePage<PageProps>().props;
    const [type, setType] = useState<MovementType>('in');

    // Mirrored here only so the on-hand line can see both at once. Each picker still
    // owns its own value and its own hidden input — this is a second reader, not a
    // second source of truth.
    const [warehouseId, setWarehouseId] = useState('');
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
            items.map((item) => ({
                value: item.value,
                primary: item.name,
                secondary: item.sku,
                group:
                    item.type === 'product'
                        ? 'stock-movements.field.item_group_product'
                        : 'stock-movements.field.item_group_raw_material',
            })),
        [items],
    );

    const schema = useMemo(
        () =>
            stockMovementSchema(
                items.map((item) => item.value),
                warehouses.map((warehouse) => warehouse.id),
                type,
            ),
        [items, warehouses, type],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={store.form()}
            schema={schema}
            title="stock-movements.create.title"
            description="stock-movements.create.description"
            submit="stock-movements.create.submit"
            submitting="stock-movements.create.submitting"
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="movement-type">
                            {t('stock-movements.field.type')}
                        </Label>

                        {/* The value the server reads. The tabs are the control; this
                            is what agrees with them, the same trick every other field
                            here uses. */}
                        <input type="hidden" name="type" value={type} />

                        <ToggleGroup
                            id="movement-type"
                            type="single"
                            variant="outline"
                            value={type}
                            // Radix clears a single-select toggle when the pressed item
                            // is pressed again. There is no "no type" here, so an empty
                            // value is ignored rather than allowed to unset it.
                            onValueChange={(next) => {
                                if (next !== '') {
                                    setType(next as MovementType);
                                }
                            }}
                            className="w-full"
                        >
                            {MOVEMENT_TYPES.map((each) => (
                                <ToggleGroupItem
                                    key={each}
                                    value={each}
                                    className="flex-1"
                                >
                                    {t(TYPE_COPY[each].tab)}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>

                        <p className="text-muted-foreground text-xs">
                            {t(TYPE_COPY[type].hint)}
                        </p>
                    </div>

                    <StockPickerField
                        name="warehouse_id"
                        label="stock-movements.field.warehouse"
                        entries={warehouseEntries}
                        placeholder="stock-movements.field.warehouse_placeholder"
                        searchPlaceholder="stock-movements.field.warehouse_search"
                        emptyMessage="stock-movements.field.warehouse_empty"
                        onChange={setWarehouseId}
                        error={errors.warehouse_id}
                    />

                    <StockPickerField
                        name="item"
                        label="stock-movements.field.item"
                        entries={itemEntries}
                        placeholder="stock-movements.field.item_placeholder"
                        searchPlaceholder="stock-movements.field.item_search"
                        emptyMessage="stock-movements.field.item_empty"
                        onChange={setItem}
                        error={errors.item}
                    />

                    <div className="space-y-2">
                        <TextField
                            name="quantity"
                            label="stock-movements.field.quantity"
                            placeholder={
                                type === 'set'
                                    ? 'stock-movements.field.quantity_placeholder_set'
                                    : 'stock-movements.field.quantity_placeholder'
                            }
                            error={errors.quantity}
                            inputMode="decimal"
                        />

                        {/* Beside the box it informs rather than up by the pickers, and
                            for `set` it is the number being replaced. */}
                        <OnHandLine warehouseId={warehouseId} item={item} />
                    </div>

                    <TextField
                        name="notes"
                        label="stock-movements.field.notes"
                        placeholder="stock-movements.field.notes_placeholder"
                        error={errors.notes}
                        optional
                        rows={2}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
