import { SelectField, type SelectOption } from '@/components/form/select-field';
import { TextField } from '@/components/form/text-field';

/** Both cases of `App.Enums.NumberReset`. See the enum for what each one does. */
const NUMBER_RESETS: SelectOption[] = [
    { value: 'yearly', label: 'business-settings.number_reset.yearly' },
    { value: 'never', label: 'business-settings.number_reset.never' },
];

/**
 * The twelve months, as the numbers the column stores.
 *
 * Written out rather than asked of `Intl`: month names differ between the SSR runtime
 * and the browser, which is a hydration mismatch nothing but a hard reload would
 * reveal — and they belong in `lang/` like every other word on the screen.
 */
const MONTHS: SelectOption[] = [
    { value: '1', label: 'business-settings.month.january' },
    { value: '2', label: 'business-settings.month.february' },
    { value: '3', label: 'business-settings.month.march' },
    { value: '4', label: 'business-settings.month.april' },
    { value: '5', label: 'business-settings.month.may' },
    { value: '6', label: 'business-settings.month.june' },
    { value: '7', label: 'business-settings.month.july' },
    { value: '8', label: 'business-settings.month.august' },
    { value: '9', label: 'business-settings.month.september' },
    { value: '10', label: 'business-settings.month.october' },
    { value: '11', label: 'business-settings.month.november' },
    { value: '12', label: 'business-settings.month.december' },
];

/**
 * The half of the settings form that decides what a document is called.
 *
 * Split from the page because the four prefixes, the reset mode and the year's first
 * month are one decision made together — `PO-2026-0001` is all three at once — and
 * because keeping them here is what holds the page itself down to a shape somebody can
 * read in one screen.
 *
 * The hint sits on the first prefix only. It explains the rule all four share, and
 * repeating it under each one would be four copies of a sentence nobody reads twice.
 */
export function DocumentNumberFields({
    settings,
    errors,
}: {
    settings: App.Data.BusinessSettingsData;
    errors: Record<string, string>;
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    name="purchase_order_prefix"
                    label="business-settings.field.purchase_order_prefix"
                    placeholder="business-settings.field.prefix_placeholder"
                    hint="business-settings.field.prefix_hint"
                    defaultValue={settings.purchase_order_prefix}
                    error={errors.purchase_order_prefix}
                />

                <TextField
                    name="purchase_return_prefix"
                    label="business-settings.field.purchase_return_prefix"
                    placeholder="business-settings.field.prefix_placeholder"
                    defaultValue={settings.purchase_return_prefix}
                    error={errors.purchase_return_prefix}
                />

                <TextField
                    name="sales_order_prefix"
                    label="business-settings.field.sales_order_prefix"
                    placeholder="business-settings.field.prefix_placeholder"
                    defaultValue={settings.sales_order_prefix}
                    error={errors.sales_order_prefix}
                />

                <TextField
                    name="sales_return_prefix"
                    label="business-settings.field.sales_return_prefix"
                    placeholder="business-settings.field.prefix_placeholder"
                    defaultValue={settings.sales_return_prefix}
                    error={errors.sales_return_prefix}
                />
            </div>

            <SelectField
                name="number_reset"
                label="business-settings.field.number_reset"
                placeholder="business-settings.field.number_reset_placeholder"
                hint="business-settings.field.number_reset_hint"
                options={NUMBER_RESETS}
                defaultValue={settings.number_reset}
                error={errors.number_reset}
            />

            {/*
                A string, because the column is an integer and a hidden input carries
                text. The pair only meets again in the FormRequest, which casts it back.
            */}
            <SelectField
                name="financial_year_start_month"
                label="business-settings.field.financial_year_start_month"
                placeholder="business-settings.field.financial_year_start_month_placeholder"
                hint="business-settings.field.financial_year_start_month_hint"
                options={MONTHS}
                defaultValue={String(settings.financial_year_start_month)}
                error={errors.financial_year_start_month}
            />
        </>
    );
}
