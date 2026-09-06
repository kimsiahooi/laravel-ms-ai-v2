import { ComboboxField } from '@/components/form/combobox-field';
import { SelectField, type SelectOption } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';
import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDateInput } from '@/lib/format';
import type { TranslationKey } from '@/types/lang';

type Order = App.Data.PurchaseOrderData;

/**
 * The names of the currencies this app knows, keyed by ISO code.
 *
 * The second copy of this map — the settings screen has the first — and deliberately a
 * copy rather than an import. A `SelectOption` label is a `TranslationKey`, so the
 * compiler proves each entry exists, and a `Record` built by lowercasing a code would
 * prove nothing and render `business-settings.currency.jpy` on screen the day a sixth
 * currency arrives. It moves to a shared module on the third copy, which is sales
 * orders; two is not yet a pattern.
 *
 * A code with no entry here is left out of the picker rather than offered under its own
 * key: adding a currency means naming it in `lang/`, and this is what says so.
 */
const CURRENCY_NAMES: Record<string, TranslationKey> = {
    MYR: 'business-settings.currency.myr',
    SGD: 'business-settings.currency.sgd',
    USD: 'business-settings.currency.usd',
    EUR: 'business-settings.currency.eur',
    CNY: 'business-settings.currency.cny',
};

/**
 * Everything about the order that is not a line: who it is with, what it is priced in,
 * when it is expected, and why.
 *
 * Split out of `form.tsx` so the page is left holding the two things only it can hold —
 * the lines and the submission — rather than a hundred lines of fields as well.
 *
 * **Every field here is uncontrolled**, seeded from `defaultValue` and left to the DOM,
 * which is how every other form in this app works and why they can reuse these
 * components unchanged. The one exception is the currency, and it earns it: the exchange
 * rate below is only a question while the order is priced in something other than the
 * workspace's own money, so one field's *existence* depends on another field's value.
 * Nothing else on the page reads any of the rest, so nothing else needs mirroring.
 */
export function OrderHeaderFields({
    order,
    suppliers,
    currencies,
    currency,
    onCurrencyChange,
    errors,
}: {
    /** The order being edited, or null while raising a new one. */
    order: Order | null;
    suppliers: App.Data.OptionData[];
    /** What this workspace may raise an order in. See {@see baseCurrency}. */
    currencies: string[];
    /** The code chosen right now — mirrored up so the lines can price themselves. */
    currency: string;
    onCurrencyChange: (currency: string) => void;
    /** Laravel's bag, or the zod gate's, keyed the way both key it. */
    errors: Record<string, string>;
}) {
    const timeZone = useTimeZone();
    const options = currencyOptions(currencies);
    const foreign = currency !== '' && currency !== baseCurrency(currencies);

    return (
        <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <ComboboxField
                    name="supplier_id"
                    label="purchase-orders.field.supplier"
                    options={suppliers}
                    defaultValue={order?.supplier_id ?? null}
                    placeholder="purchase-orders.field.supplier_placeholder"
                    searchPlaceholder="purchase-orders.field.supplier_search"
                    emptyMessage="purchase-orders.field.supplier_empty"
                    error={errors.supplier_id}
                />

                <SelectField
                    name="currency"
                    label="purchase-orders.field.currency"
                    options={options}
                    defaultValue={currency}
                    placeholder="purchase-orders.field.currency_placeholder"
                    onChange={onCurrencyChange}
                    error={errors.currency}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {/*
                    Only while the order is in a foreign currency, because only then is
                    there anything to answer. A rate box on a MYR order in a workspace
                    that keeps its books in MYR asks "how many ringgit to the ringgit",
                    and the one answer it accepts is 1 — so the form stops asking and
                    `form.tsx` sends the 1 itself.
                */}
                {foreign && (
                    <TextField
                        name="exchange_rate"
                        label="purchase-orders.field.exchange_rate"
                        hint="purchase-orders.field.exchange_rate_hint"
                        placeholder="purchase-orders.field.exchange_rate_placeholder"
                        inputMode="decimal"
                        defaultValue={order?.exchange_rate ?? ''}
                        error={errors.exchange_rate}
                    />
                )}

                <TextField
                    name="expected_date"
                    type="date"
                    label="purchase-orders.field.expected_date"
                    hint="purchase-orders.field.expected_date_hint"
                    // The stored instant back onto this browser's clock. A date input
                    // takes `Y-m-d` and renders EMPTY for anything else without a
                    // word of complaint, so handing it the ISO string looked like an
                    // order with no expected date rather than like a bug.
                    defaultValue={
                        order?.expected_date == null
                            ? ''
                            : formatDateInput(order.expected_date, timeZone)
                    }
                    error={errors.expected_date}
                    optional
                />
            </div>

            <TextField
                name="notes"
                label="purchase-orders.field.notes"
                placeholder="purchase-orders.field.notes_placeholder"
                defaultValue={order?.notes ?? ''}
                error={errors.notes}
                rows={3}
                optional
            />
        </div>
    );
}

/**
 * The workspace's own money — the first code in the list, and not by luck.
 *
 * `BusinessSetting::allowedCurrencies()` builds the list as `[base, ...chosen]` and
 * dedupes, precisely so the base is always offerable; the ordering is the same fact
 * looked at from here. Sending it as its own prop would be a second copy of it, and the
 * two could disagree.
 */
export function baseCurrency(currencies: string[]): string {
    return currencies[0] ?? '';
}

/** The codes this workspace allows, in the order it allows them, named in `lang/`. */
function currencyOptions(currencies: string[]): SelectOption[] {
    return currencies
        .filter((code) => code in CURRENCY_NAMES)
        .map((code) => ({ value: code, label: CURRENCY_NAMES[code] }));
}
