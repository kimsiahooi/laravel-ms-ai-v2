import { ClipboardList } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { StockPickerField } from '@/components/form/stock-picker-field';
import { TextField } from '@/components/form/text-field';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { stockTakeSchema } from '@/lib/validation/schemas/stock-take';
import { store } from '@/routes/stock-takes';

/**
 * The one way to start a count.
 *
 * Renders nothing without the permission and nothing with no warehouses — there is
 * nowhere to count, and a dialog whose only required field has no valid answer is
 * worse than a button that is not there. The hooks run first regardless, because a
 * conditional hook is a different component on the next render.
 *
 * Notes are on the create form rather than left for later on purpose: the reason a
 * count is being taken — a quarter end, a suspected loss — is known now and forgotten
 * by the time it is posted.
 */
export function NewTakeButton({
    warehouses,
}: {
    warehouses: App.Data.WarehouseOptionData[];
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [open, setOpen] = useState(false);

    // Two lines per entry, so two sites with a "Main store" stay tellable apart — the
    // reason this picker exists rather than ComboboxField.
    const entries: StockPickerEntry[] = useMemo(
        () =>
            warehouses.map((warehouse) => ({
                value: String(warehouse.id),
                primary: warehouse.name,
                secondary: warehouse.site,
            })),
        [warehouses],
    );

    const schema = useMemo(
        () => stockTakeSchema(warehouses.map((warehouse) => warehouse.id)),
        [warehouses],
    );

    if (!can('stock-takes.create') || warehouses.length === 0) {
        return null;
    }

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <ClipboardList className="size-4" />
                {t('stock-takes.action.new')}
            </Button>

            <ResourceFormDialog
                open={open}
                onOpenChange={setOpen}
                action={store.form()}
                schema={schema}
                title="stock-takes.dialog.create.title"
                description="stock-takes.dialog.create.description"
                submit="stock-takes.dialog.create.submit"
                submitting="stock-takes.dialog.create.submitting"
            >
                {({ errors }) => (
                    <div className="space-y-4">
                        <StockPickerField
                            name="warehouse_id"
                            label="stock-takes.field.warehouse"
                            entries={entries}
                            placeholder="stock-takes.field.warehouse_placeholder"
                            searchPlaceholder="stock-takes.field.warehouse_search"
                            emptyMessage="stock-takes.field.warehouse_empty"
                            error={errors.warehouse_id}
                        />

                        <TextField
                            name="notes"
                            label="stock-takes.field.notes"
                            placeholder="stock-takes.field.notes_placeholder"
                            error={errors.notes}
                            optional
                            rows={2}
                        />
                    </div>
                )}
            </ResourceFormDialog>
        </>
    );
}
