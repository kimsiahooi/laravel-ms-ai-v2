<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\NumberReset;
use App\Models\BusinessSetting;
use App\Support\DocumentNumberGenerator;
use App\Support\TimeZones;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The one write the business settings screen makes.
 *
 * **No zod counterpart, and that is deliberate** — it is named in the EXEMPT set of
 * `scripts/check-validation-parity.ts`. Every field here is a picker, a short label or
 * a number the browser cannot judge better than the server can: the currency lists are
 * the server's own catalog, the reset mode is an enum, and the month is one of twelve.
 * A second copy of that in TypeScript would be a list to keep in step with no failure
 * for it to prevent.
 *
 * **The prefix rules are written out four times rather than shared through a helper.**
 * `bun run check:i18n` reads these arrays as text to prove every rule in use has a
 * translated message, and a rule hidden behind `$this->prefixRules()` is a rule the
 * gate cannot see — so the repetition is what keeps `regex` and `max` visible to it.
 *
 * What is deliberately NOT a rule here: that the base currency appears in the allowed
 * list. {@see BusinessSetting::allowedCurrencies()} folds it in on the way out, so a
 * workspace cannot reach the invalid state whether the form sends it or not, and
 * refusing the submission would only teach people to tick a box that changes nothing.
 */
final class SettingsUpdateRequest extends TenantFormRequest
{
    /**
     * A financial year starts in one of twelve months. A range rather than a min and a
     * max, because "the selected month is invalid" is the honest answer for a picker —
     * nobody types this field.
     *
     * @var list<int>
     */
    private const MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // The catalog, from the model rather than from a list of its own: the same five
        // codes seed a new workspace, and two lists that could disagree would mean a
        // workspace seeded with a currency this screen refuses to save back.
        $currencies = BusinessSetting::defaultCurrencies();

        return [
            'base_currency' => ['required', 'string', Rule::in($currencies)],
            // `required` is what refuses an empty list — Laravel counts `[]` as absent
            // — so a workspace cannot save its way into having no currency at all.
            'currencies' => ['required', 'array'],
            'currencies.*' => ['string', 'distinct', Rule::in($currencies)],
            // A percentage, so the ceiling is 100 rather than the column's eleven
            // integer digits. The second `max` is not a mistake: decimalRules() caps at
            // what `decimal(8,4)` can hold, and this caps at what a tax rate can mean.
            'tax_rate' => ['required', ...$this->decimalRules('gte:0'), 'max:100'],
            'tax_label' => ['required', 'string', 'max:20'],
            'purchase_order_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
            'purchase_return_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
            'sales_order_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
            'sales_return_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9-]+$/'],
            'number_reset' => ['required', Rule::enum(NumberReset::class)],
            'financial_year_start_month' => ['required', 'integer', Rule::in(self::MONTHS)],
            // Checked against the tzdb rather than by length or shape. This value reaches
            // `Intl.DateTimeFormat` in the SSR process exactly as the browser's cookie
            // does, and an identifier that runtime does not know throws a RangeError
            // mid-render — TimeZones::supports() is the same guard, applied to the same
            // hazard from the other direction.
            'timezone' => ['required', 'string', $this->timeZoneExists()],
        ];
    }

    /**
     * A zone the tzdb knows.
     *
     * A closure rather than `Rule::in(...)`, which would put four hundred identifiers
     * into the rule and again into the failed-validation payload. {@see TimeZones::supports}
     * is the same check the browser's cookie goes through, and it is the check that
     * matters: this value reaches `Intl.DateTimeFormat` inside the SSR process, which
     * throws a RangeError on an identifier it does not know and takes the render down.
     *
     * `validation.exists` because that is what this is — the selected value is not one of
     * the ones there are — and it is already translated in all three locales.
     */
    private function timeZoneExists(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! TimeZones::supports($value)) {
                $fail('validation.exists')->translate();
            }
        };
    }

    /**
     * `validation.regex` says only "the format is invalid", which leaves somebody who
     * typed "PO 2026" guessing. {@see DocumentNumberGenerator} joins the prefix to the
     * year and the count with hyphens, so the one thing worth saying is which
     * characters survive that.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_prefix.regex' => __('business-settings.validation.prefix'),
            'purchase_return_prefix.regex' => __('business-settings.validation.prefix'),
            'sales_order_prefix.regex' => __('business-settings.validation.prefix'),
            'sales_return_prefix.regex' => __('business-settings.validation.prefix'),
        ];
    }
}
