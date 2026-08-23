import { SelectField } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';

type Customer = App.Data.CustomerData;
type Country = App.Enums.Country;

/**
 * The billing address, broken out rather than left as one text box. MyInvois and
 * InvoiceNow both want city, postcode, state and country as separate fields, and a
 * single free-text address cannot be split back into them afterwards.
 *
 * The country list comes from the server — see App\Enums\Country — so the picker and
 * the validation rule can never offer different codes.
 */
export function CustomerAddressFields({
    customer,
    countries,
    errors,
}: {
    customer?: Customer;
    countries: Country[];
    errors: Record<string, string>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
                <TextField
                    name="address"
                    label="customers.field.address"
                    placeholder="customers.field.address_placeholder"
                    defaultValue={customer?.address}
                    error={errors.address}
                    optional
                    rows={2}
                />
            </div>

            <TextField
                name="city"
                label="customers.field.city"
                placeholder="customers.field.city_placeholder"
                defaultValue={customer?.city}
                error={errors.city}
                optional
            />

            <TextField
                name="postcode"
                label="customers.field.postcode"
                placeholder="customers.field.postcode_placeholder"
                defaultValue={customer?.postcode}
                error={errors.postcode}
                optional
            />

            <TextField
                name="state_code"
                label="customers.field.state_code"
                placeholder="customers.field.state_code_placeholder"
                defaultValue={customer?.state_code}
                error={errors.state_code}
                optional
            />

            <SelectField
                name="country_code"
                label="customers.field.country_code"
                placeholder="customers.field.country_code_placeholder"
                defaultValue={customer?.country_code}
                error={errors.country_code}
                optional
                options={countries.map((code) => ({
                    value: code,
                    // The names live in lang/{locale}/countries.php, keyed by code.
                    label: `countries.${code}` as const,
                }))}
            />
        </div>
    );
}
