<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\Unit;
use App\Models\PurchaseReturnItem;
use App\Support\Decimals;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a saved return, as the document reads it.
 *
 * **`ordered` is here for context, not for arithmetic.** Reading "2 of the 5 that arrived" is
 * the whole story of a return line, and the alternative is opening the order in another tab.
 * The ceiling itself is not shown on a saved document — it is a question about what may still
 * be raised, which belongs on the form.
 *
 * The identity comes from `purchaseOrderItem.rawMaterial`, two hops through relations that
 * include archived rows, so an archived material still names itself. All three go null together
 * in the one case: the material was hard-deleted out of the catalogue, and the line still says
 * what was credited.
 *
 * `$currency` is a parameter for the reason {@see PurchaseOrderItemData} takes one: a line has
 * no currency of its own, and `line_total` has to render byte-for-byte like the preview the form
 * showed before it was saved.
 */
#[TypeScript]
final class PurchaseReturnItemData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $sku,
        public ?Unit $unit,
        /** What the delivery brought — context for the quantity beside it. */
        public string $ordered,
        public string $quantity,
        public string $unit_cost,
        public DiscountType $discount_type,
        public string $discount_value,
        public bool $taxable,
        public string $line_total,
    ) {}

    public static function fromPurchaseReturnItem(PurchaseReturnItem $item, string $currency): self
    {
        $line = $item->purchaseOrderItem;
        $material = $line->rawMaterial;

        return new self(
            id: $item->id,
            name: $material?->name,
            sku: $material?->sku,
            unit: $material?->unit,
            ordered: Decimals::trim($line->quantity),
            // Trimmed rather than rounded, for the reason PurchaseOrderItemData gives: a unit
            // cost genuinely uses its fourth place, so rounding it to the currency here would
            // destroy the number rather than tidy it.
            quantity: Decimals::trim($item->quantity),
            unit_cost: Decimals::trim($item->unit_cost),
            discount_type: $item->discount_type,
            discount_value: Decimals::trim($item->discount_value),
            taxable: $item->taxable,
            line_total: Money::roundTo($item->line_total, $currency),
        );
    }
}
