import { TextField } from '@/components/form/text-field';

type Customer = App.Data.CustomerData;

/**
 * The identifiers an e-invoice needs for the buyer. Optional here on purpose — a
 * customer is usually added mid-conversation, long before anyone has their TIN, and a
 * form that insisted would be filled with rubbish.
 */
export function CustomerTaxFields({
    customer,
    errors,
}: {
    customer?: Customer;
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <TextField
                name="tin"
                label="customers.field.tin"
                placeholder="customers.field.tin_placeholder"
                defaultValue={customer?.tin}
                error={errors.tin}
                optional
            />

            <TextField
                name="registration_no"
                label="customers.field.registration_no"
                placeholder="customers.field.registration_no_placeholder"
                defaultValue={customer?.registration_no}
                error={errors.registration_no}
                optional
            />

            <div className="sm:col-span-2">
                <TextField
                    name="sst_registration_no"
                    label="customers.field.sst_registration_no"
                    placeholder="customers.field.sst_registration_no_placeholder"
                    defaultValue={customer?.sst_registration_no}
                    error={errors.sst_registration_no}
                    optional
                />
            </div>
        </div>
    );
}
