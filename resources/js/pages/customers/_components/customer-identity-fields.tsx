import { TextField } from '@/components/form/text-field';

type Customer = App.Data.CustomerData;

/** Who they are and how you reach them — the part anyone can fill in from memory. */
export function CustomerIdentityFields({
    customer,
    errors,
}: {
    customer?: Customer;
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <TextField
                    name="name"
                    label="customers.field.name"
                    placeholder="customers.field.name_placeholder"
                    defaultValue={customer?.name}
                    error={errors.name}
                    autoFocus
                    autoComplete="organization"
                />
            </div>

            <TextField
                name="contact_person"
                label="customers.field.contact_person"
                placeholder="customers.field.contact_person_placeholder"
                defaultValue={customer?.contact_person}
                error={errors.contact_person}
                optional
            />

            <TextField
                name="email"
                type="email"
                label="customers.field.email"
                placeholder="customers.field.email_placeholder"
                defaultValue={customer?.email}
                error={errors.email}
                optional
            />

            <TextField
                name="phone"
                type="tel"
                label="customers.field.phone"
                placeholder="customers.field.phone_placeholder"
                defaultValue={customer?.phone}
                error={errors.phone}
                optional
            />
        </div>
    );
}
