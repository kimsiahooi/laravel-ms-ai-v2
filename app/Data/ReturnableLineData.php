<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\Unit;
use App\Http\Controllers\Tenant\PurchaseReturnController;
use App\Models\PurchaseOrderItem;
use App\Support\Decimals;
use App\Support\ReturnedQuantities;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One delivered line as the return form offers it: what arrived, what has already gone back,
 * what is left, and the money that would be credited.
 *
 * **Every returnable line becomes a row, and the blank ones are how you say no.** The form does
 * not pick lines from a picker — the order decides which rows exist, and typing a quantity is
 * what puts one on the return. So this object is the whole of a row, and a row is readable
 * without a second lookup.
 *
 * **`returned` excludes the return being edited, and that is the point of it.** On an edit,
 * "already returned" must mean "by everybody else", or the remaining figure beside it would not
 * add up and the form would refuse a return its own quantities. The exclusion happens once, in
 * {@see PurchaseReturnController::returnableLines()}, against the same ceiling the FormRequest's
 * after-hook will enforce — so the screen and the server agree by construction rather than by
 * coincidence.
 *
 * **The money travels even though nobody types it.** `components/form/order-totals-summary.tsx`
 * needs a quantity, a price, a discount and a taxable flag per row to preview what the return
 * comes to, and four of those five are the delivered line's, unchanged. The fifth is the only
 * number on this screen a person supplies.
 *
 * `name`, `sku` and `unit` are null in exactly one case: the material was hard-deleted out of
 * the catalogue. The line still says what was paid, the way {@see PurchaseOrderItemData} does.
 */
#[TypeScript]
final class ReturnableLineData extends Data
{
    public function __construct(
        /** The delivered line this row would credit — what the form posts back. */
        public int $purchase_order_item_id,
        public ?string $name,
        public ?string $sku,
        public ?Unit $unit,
        /** What the delivery brought. */
        public string $ordered,
        /** What every OTHER return has already taken off this line. */
        public string $returned,
        /** `ordered − returned`, floored at zero — the ceiling for this row. */
        public string $remaining,
        /** What THIS return already holds for the line. Empty while creating one. */
        public string $quantity,
        public string $unit_cost,
        public DiscountType $discount_type,
        public string $discount_value,
        public bool $taxable,
    ) {}

    /**
     * @param  string  $returned  from {@see ReturnedQuantities::forOrderItems()}, already
     *                            excluding the return being edited
     * @param  string  $quantity  what this return holds for the line, or `''`
     */
    public static function fromOrderItem(PurchaseOrderItem $line, string $returned, string $quantity): self
    {
        // Loaded withTrashed by the relation, so a material archived after the delivery still
        // names itself. A hard delete nulls the FK and leaves the money intact.
        $material = $line->rawMaterial;

        return new self(
            purchase_order_item_id: $line->id,
            name: $material?->name,
            sku: $material?->sku,
            unit: $material?->unit,
            // Trimmed, all of them: every one of these is read beside an input or put into
            // one, and `12.0000` in a column of quantities is four digits of noise per row.
            ordered: Decimals::trim($line->quantity),
            returned: Decimals::trim($returned),
            remaining: Decimals::trim(ReturnedQuantities::remaining($line->quantity, $returned)),
            quantity: $quantity === '' ? '' : Decimals::trim($quantity),
            unit_cost: Decimals::trim($line->unit_cost),
            discount_type: $line->discount_type,
            discount_value: Decimals::trim($line->discount_value),
            taxable: $line->taxable,
        );
    }
}
