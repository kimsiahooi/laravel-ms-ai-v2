import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { ComboboxField } from '@/components/form/combobox-field';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { warehouseSchema } from '@/lib/validation/schemas/warehouse';
import { store, update } from '@/routes/warehouses';

type Warehouse = App.Data.WarehouseData;

type PageProps = { locations: App.Data.OptionData[] };

/**
 * Four fields, the site among them. The dialog, the submission, the gate and the
 * footer belong to {@see ResourceFormDialog}.
 *
 * The site comes first because it is the only required choice and the only one that
 * cannot be changed casually later — moving a warehouse moves its stock with it.
 */
export function WarehouseFormDialog({
    open,
    onOpenChange,
    warehouse,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    warehouse?: Warehouse;
}) {
    // Off the page rather than through props: the row menus that open this dialog are
    // rendered by TanStack column definitions built at module scope, which cannot
    // close over page data. See ProductFormDialog, which reads its pickers the same way.
    const { locations } = usePage<PageProps>().props;
    const editing = warehouse !== undefined;

    const schema = useMemo(
        () => warehouseSchema(locations.map((location) => location.id)),
        [locations],
    );

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing
                    ? update.form({ warehouse: warehouse.id })
                    : store.form()
            }
            schema={schema}
            title={
                editing ? 'warehouses.edit.title' : 'warehouses.create.title'
            }
            description={
                editing
                    ? 'warehouses.edit.description'
                    : 'warehouses.create.description'
            }
            submit={
                editing ? 'warehouses.edit.submit' : 'warehouses.create.submit'
            }
            submitting={
                editing
                    ? 'warehouses.edit.submitting'
                    : 'warehouses.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <ComboboxField
                        name="location_id"
                        label="warehouses.field.site"
                        placeholder="warehouses.field.site_placeholder"
                        searchPlaceholder="warehouses.field.site_search"
                        emptyMessage="warehouses.field.site_empty"
                        hint="warehouses.field.site_hint"
                        options={locations}
                        defaultValue={warehouse?.location_id}
                        error={errors.location_id}
                    />

                    <TextField
                        name="name"
                        label="warehouses.field.name"
                        placeholder="warehouses.field.name_placeholder"
                        defaultValue={warehouse?.name}
                        error={errors.name}
                    />

                    <TextField
                        name="code"
                        label="warehouses.field.code"
                        placeholder="warehouses.field.code_placeholder"
                        hint="warehouses.field.code_hint"
                        defaultValue={warehouse?.code}
                        error={errors.code}
                        optional
                    />

                    <TextField
                        name="address"
                        label="warehouses.field.address"
                        placeholder="warehouses.field.address_placeholder"
                        defaultValue={warehouse?.address}
                        error={errors.address}
                        optional
                        rows={3}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
