<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NumberReset;
use App\Models\BusinessSetting;
use App\Support\Decimals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The workspace's money settings, as the settings form reads them back.
 *
 * **`currencies` is the effective list, not the stored column.** It comes from
 * {@see BusinessSetting::allowedCurrencies()}, which folds the base currency in
 * whatever the column says — so the form opens showing what an order may actually be
 * raised in rather than what happens to be on disk. Sending the raw column instead
 * would let the screen render a workspace whose books are kept in a currency it shows
 * as unticked, which is a state nothing else in the app believes in.
 *
 * There is no `id` and no timestamps. One row exists, the screen addresses it as "the
 * settings", and a hidden id on a form that can only ever write that row is a field
 * somebody would eventually be tempted to trust.
 */
#[TypeScript]
final class BusinessSettingsData extends Data
{
    public function __construct(
        public string $base_currency,
        /** @var list<string> */
        public array $currencies,
        public string $tax_rate,
        public string $tax_label,
        public string $purchase_order_prefix,
        public string $purchase_return_prefix,
        public string $sales_order_prefix,
        public string $sales_return_prefix,
        public NumberReset $number_reset,
        public int $financial_year_start_month,
    ) {}

    public static function fromSettings(BusinessSetting $settings): self
    {
        return new self(
            base_currency: $settings->base_currency,
            currencies: $settings->allowedCurrencies(),
            // Trimmed, like every other decimal this app puts in front of a person:
            // the column always answers 6.0000, and a tax box pre-filled with three
            // trailing zeros invites somebody to retype the whole number to change 6
            // to 8. `Decimals::trim` is the same helper the stock screens use.
            tax_rate: Decimals::trim($settings->tax_rate),
            tax_label: $settings->tax_label,
            purchase_order_prefix: $settings->purchase_order_prefix,
            purchase_return_prefix: $settings->purchase_return_prefix,
            sales_order_prefix: $settings->sales_order_prefix,
            sales_return_prefix: $settings->sales_return_prefix,
            number_reset: $settings->number_reset,
            financial_year_start_month: $settings->financial_year_start_month,
        );
    }
}
