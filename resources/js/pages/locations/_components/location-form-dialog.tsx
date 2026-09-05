import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { locationSchema } from '@/lib/validation/schemas/location';
import { store, update } from '@/routes/locations';

type Location = App.Data.LocationData;

/**
 * Three fields and the words around them. The dialog, the submission, the gate and
 * the footer belong to {@see ResourceFormDialog}; the label/error/aria wiring belongs
 * to {@see TextField}.
 *
 * Create and edit are one component: the only difference is which route the form
 * posts to and which four strings it shows.
 */
export function LocationFormDialog({
    open,
    onOpenChange,
    location,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    location?: Location;
}) {
    const editing = location !== undefined;

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing ? update.form({ location: location.id }) : store.form()
            }
            schema={locationSchema}
            title={editing ? 'locations.edit.title' : 'locations.create.title'}
            description={
                editing
                    ? 'locations.edit.description'
                    : 'locations.create.description'
            }
            submit={
                editing ? 'locations.edit.submit' : 'locations.create.submit'
            }
            submitting={
                editing
                    ? 'locations.edit.submitting'
                    : 'locations.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <TextField
                        name="name"
                        label="locations.field.name"
                        placeholder="locations.field.name_placeholder"
                        defaultValue={location?.name}
                        error={errors.name}
                        autoFocus
                    />

                    <TextField
                        name="code"
                        label="locations.field.code"
                        placeholder="locations.field.code_placeholder"
                        hint="locations.field.code_hint"
                        defaultValue={location?.code}
                        error={errors.code}
                        optional
                    />

                    <TextField
                        name="address"
                        label="locations.field.address"
                        placeholder="locations.field.address_placeholder"
                        defaultValue={location?.address}
                        error={errors.address}
                        optional
                        rows={3}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
