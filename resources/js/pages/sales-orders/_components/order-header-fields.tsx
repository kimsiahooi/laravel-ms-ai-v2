import { ComboboxField } from '@/components/form/combobox-field';
import { DateField } from '@/components/form/date-field';
import { SelectField } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { baseCurrency, currencyOptions } from '@/config/currencies';

type Order = App.Data.SalesOrderData;

/**
 * Everything about the order that is not a line: who it is for, what it is priced in, when
 * it was promised, and why.
 *
 * Split out of `form.tsx` so the page is left holding the two things only it can hold — the
 * lines and the submission — rather than a hundred lines of fields as well.
 *
 * **Every field here is uncontrolled**, seeded from `defaultValue` and left to the DOM,
 * which is how every other form in this app works and why they can reuse these components
 * unchanged. The one exception is the currency, and it earns it: the exchange rate below is
 * only a question while the order is priced in something other than the workspace's own
 * money, so one field's *existence* depends on another field's value. Nothing else on the
 * page reads any of the rest, so nothing else needs mirroring.
 */
export function OrderHeaderFields({
    order,
    customers,
    currencies,
    currency,
    onCurrencyChange,
    errors,
}: {
    /** The order being edited, or null while taking a new one. */
    order: Order | null;
    customers: App.Data.OptionData[];
    /** What this workspace may take an order in. See {@see baseCurrency}. */
    currencies: string[];
    /** The code chosen right now — mirrored up so the lines can price themselves. */
    currency: string;
    onCurrencyChange: (currency: string) => void;
    /** Laravel's bag, or the zod gate's, keyed the way both key it. */
    errors: Record<string, string>;
}) {
    const options = currencyOptions(currencies);
    const foreign = currency !== '' && currency !== baseCurrency(currencies);

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <ComboboxField
                    name="customer_id"
                    label="sales-orders.field.customer"
                    options={customers}
                    defaultValue={order?.customer_id ?? null}
                    placeholder="sales-orders.field.customer_placeholder"
                    searchPlaceholder="sales-orders.field.customer_search"
                    emptyMessage="sales-orders.field.customer_empty"
                    error={errors.customer_id}
                />

                <SelectField
                    name="currency"
                    label="sales-orders.field.currency"
                    options={options}
                    defaultValue={currency}
                    placeholder="sales-orders.field.currency_placeholder"
                    onChange={onCurrencyChange}
                    error={errors.currency}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {/*
                    Only while the order is in a foreign currency, because only then is
                    there anything to answer. A rate box on a MYR order in a workspace that
                    keeps its books in MYR asks "how many ringgit to the ringgit", and the
                    one answer it accepts is 1 — so the form stops asking and `form.tsx`
                    sends the 1 itself.
                */}
                {foreign && (
                    <TextField
                        name="exchange_rate"
                        label="sales-orders.field.exchange_rate"
                        hint="sales-orders.field.exchange_rate_hint"
                        placeholder="sales-orders.field.exchange_rate_placeholder"
                        inputMode="decimal"
                        defaultValue={order?.exchange_rate ?? ''}
                        error={errors.exchange_rate}
                    />
                )}

                <DateField
                    name="expected_date"
                    label="sales-orders.field.expected_date"
                    hint="sales-orders.field.expected_date_hint"
                    defaultValue={order?.expected_date ?? ''}
                    error={errors.expected_date}
                    withTime
                    optional
                />
            </div>

            <TextField
                name="notes"
                label="sales-orders.field.notes"
                placeholder="sales-orders.field.notes_placeholder"
                defaultValue={order?.notes ?? ''}
                error={errors.notes}
                rows={3}
                optional
            />
        </div>
    );
}
