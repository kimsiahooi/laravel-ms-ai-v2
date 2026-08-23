import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo } from 'react';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { customerSchema } from '@/lib/validation/schemas/customer';
import { CustomerAddressFields } from '@/pages/customers/_components/customer-address-fields';
import { CustomerIdentityFields } from '@/pages/customers/_components/customer-identity-fields';
import { CustomerTaxFields } from '@/pages/customers/_components/customer-tax-fields';
import { store, update } from '@/routes/customers';
import type { TranslationKey } from '@/types/lang';

type Customer = App.Data.CustomerData;
type PageProps = { countries: App.Enums.Country[] };

/**
 * Thirteen fields, in three named groups.
 *
 * The grouping is the whole design. Ungrouped, this reads as a wall of boxes and people
 * fill in the first four and give up; named, it says which parts are for talking to
 * someone and which are for invoicing them, and abandoning the last two groups becomes
 * an informed choice rather than fatigue.
 *
 * The field groups themselves live in their own files — not to hit a line count, but
 * because "the billing address" is a thing with its own rules that will be wanted again
 * when a supplier needs one.
 */
export function CustomerFormDialog({
    open,
    onOpenChange,
    customer,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    customer?: Customer;
}) {
    const { countries } = usePage<PageProps>().props;
    const editing = customer !== undefined;

    // Memoised on the codes, so the schema is one value for the life of the page rather
    // than a new one each render — see the note on customerSchema.
    const schema = useMemo(() => customerSchema(countries), [countries]);

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing ? update.form({ customer: customer.id }) : store.form()
            }
            schema={schema}
            size="lg"
            title={editing ? 'customers.edit.title' : 'customers.create.title'}
            description={
                editing
                    ? 'customers.edit.description'
                    : 'customers.create.description'
            }
            submit={
                editing ? 'customers.edit.submit' : 'customers.create.submit'
            }
            submitting={
                editing
                    ? 'customers.edit.submitting'
                    : 'customers.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-6">
                    <Group title="customers.group.identity">
                        <CustomerIdentityFields
                            customer={customer}
                            errors={errors}
                        />
                    </Group>

                    <Separator />

                    <Group
                        title="customers.group.tax"
                        hint="customers.group.tax_hint"
                    >
                        <CustomerTaxFields
                            customer={customer}
                            errors={errors}
                        />
                    </Group>

                    <Separator />

                    <Group title="customers.group.address">
                        <CustomerAddressFields
                            customer={customer}
                            countries={countries}
                            errors={errors}
                        />
                    </Group>

                    <Separator />

                    <TextField
                        name="notes"
                        label="customers.field.notes"
                        placeholder="customers.field.notes_placeholder"
                        defaultValue={customer?.notes}
                        error={errors.notes}
                        optional
                        rows={2}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}

/** A named run of fields, with an optional line explaining what it is for. */
function Group({
    title,
    hint,
    children,
}: {
    title: TranslationKey;
    hint?: TranslationKey;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <section className="space-y-4">
            <div className="space-y-1">
                <h3 className="font-medium text-sm">{t(title)}</h3>
                {hint && (
                    <p className="text-muted-foreground text-xs">{t(hint)}</p>
                )}
            </div>
            {children}
        </section>
    );
}
