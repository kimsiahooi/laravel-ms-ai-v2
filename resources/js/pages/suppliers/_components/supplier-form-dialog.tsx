import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { supplierSchema } from '@/lib/validation/schemas/supplier';
import { store, update } from '@/routes/suppliers';

type Supplier = App.Data.SupplierData;

/**
 * Seven fields and the words around them. The dialog, the submission, the gate and the
 * footer belong to {@see ResourceFormDialog}; the label/error/aria wiring belongs to
 * {@see TextField}.
 *
 * Only the company name is required. Everything else is what you happen to know when
 * you add them, and a form that refused to save until you knew all of it would just get
 * filled with placeholder text.
 *
 * The short fields pair up on a wider screen and stack on a phone; address and notes
 * always take the full width, because a line of an address wraps badly in half a
 * dialog.
 */
export function SupplierFormDialog({
    open,
    onOpenChange,
    supplier,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    supplier?: Supplier;
}) {
    const editing = supplier !== undefined;

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing ? update.form({ supplier: supplier.id }) : store.form()
            }
            schema={supplierSchema}
            size="lg"
            title={editing ? 'suppliers.edit.title' : 'suppliers.create.title'}
            description={
                editing
                    ? 'suppliers.edit.description'
                    : 'suppliers.create.description'
            }
            submit={
                editing ? 'suppliers.edit.submit' : 'suppliers.create.submit'
            }
            submitting={
                editing
                    ? 'suppliers.edit.submitting'
                    : 'suppliers.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <TextField
                            name="name"
                            label="suppliers.field.name"
                            placeholder="suppliers.field.name_placeholder"
                            defaultValue={supplier?.name}
                            error={errors.name}
                            autoFocus
                            autoComplete="organization"
                        />
                    </div>

                    <TextField
                        name="contact_person"
                        label="suppliers.field.contact_person"
                        placeholder="suppliers.field.contact_person_placeholder"
                        defaultValue={supplier?.contact_person}
                        error={errors.contact_person}
                        optional
                    />

                    <TextField
                        name="email"
                        type="email"
                        label="suppliers.field.email"
                        placeholder="suppliers.field.email_placeholder"
                        defaultValue={supplier?.email}
                        error={errors.email}
                        optional
                    />

                    <TextField
                        name="phone"
                        type="tel"
                        label="suppliers.field.phone"
                        placeholder="suppliers.field.phone_placeholder"
                        defaultValue={supplier?.phone}
                        error={errors.phone}
                        optional
                    />

                    <TextField
                        name="tax_id"
                        label="suppliers.field.tax_id"
                        placeholder="suppliers.field.tax_id_placeholder"
                        defaultValue={supplier?.tax_id}
                        error={errors.tax_id}
                        optional
                    />

                    <div className="sm:col-span-2">
                        <TextField
                            name="address"
                            label="suppliers.field.address"
                            placeholder="suppliers.field.address_placeholder"
                            defaultValue={supplier?.address}
                            error={errors.address}
                            optional
                            rows={2}
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <TextField
                            name="notes"
                            label="suppliers.field.notes"
                            placeholder="suppliers.field.notes_placeholder"
                            defaultValue={supplier?.notes}
                            error={errors.notes}
                            optional
                            rows={2}
                        />
                    </div>
                </div>
            )}
        </ResourceFormDialog>
    );
}
