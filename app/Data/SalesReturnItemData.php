<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\Unit;
use App\Models\SalesReturnItem;
use App\Support\Decimals;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a saved sales return, as the document screen reads it.
 *
 * The mirror of {@see PurchaseReturnItemData}. `name`, `sku` and `unit` come through
 * `salesOrderItem.product`, so an archived product still names itself and a hard-deleted one
 * leaves the line saying what was charged.
 *
 * `$currency` is a parameter for the reason {@see SalesOrderItemData} takes one: a line has no
 * currency of its own, and `line_total` has to render byte-for-byte like the preview the form
 * showed before it was saved.
 */
#[TypeScript]
final class SalesReturnItemData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $sku,
        public ?Unit $unit,
        /** What the despatch sent out — context for the quantity beside it. */
        public string $sold,
        public string $quantity,
        public string $unit_price,
        public DiscountType $discount_type,
        public string $discount_value,
        public bool $taxable,
        public string $line_total,
    ) {}

    public static function fromSalesReturnItem(SalesReturnItem $item, string $currency): self
    {
        $line = $item->salesOrderItem;
        $product = $line->product;

        return new self(
            id: $item->id,
            name: $product?->name,
            sku: $product?->sku,
            unit: $product?->unit,
            sold: Decimals::trim($line->quantity),
            // Trimmed rather than rounded, for the reason SalesOrderItemData gives: a unit
            // price genuinely uses its fourth place, so rounding it to the currency here would
            // destroy the number rather than tidy it.
            quantity: Decimals::trim($item->quantity),
            unit_price: Decimals::trim($item->unit_price),
            discount_type: $item->discount_type,
            discount_value: Decimals::trim($item->discount_value),
            taxable: $item->taxable,
            line_total: Money::roundTo($item->line_total, $currency),
        );
    }
}
