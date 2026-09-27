<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\Unit;
use App\Http\Controllers\Tenant\SalesReturnController;
use App\Models\SalesOrderItem;
use App\Support\Decimals;
use App\Support\ReturnedQuantities;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One despatched line as the return form offers it: what went out, what has already come back,
 * what is left, and the money that would be credited.
 *
 * The mirror of {@see ReturnablePurchaseLineData}, and every note on that class applies here.
 * **Every returnable line becomes a row, and the blank ones are how you say no.**
 * **`returned` excludes the return being edited**, once, in
 * {@see SalesReturnController::returnableLines()}, against the same ceiling the FormRequest's
 * after-hook will enforce. **The money travels even though nobody types it**, because the totals
 * preview needs a quantity, a price, a discount and a taxable flag per row.
 *
 * The two differences from its opposite number are the two fields the form posts and reads:
 * `sales_order_item_id` rather than the purchase line's id, and `unit_price` rather than
 * `unit_cost` — a sale has a price, a purchase has a cost, and the two documents must not borrow
 * each other's word for it.
 *
 * `name`, `sku` and `unit` are null in exactly one case: the product was hard-deleted out of the
 * catalogue. The line still says what was charged.
 */
#[TypeScript]
final class ReturnableSalesLineData extends Data
{
    public function __construct(
        /** The despatched line this row would credit — what the form posts back. */
        public int $sales_order_item_id,
        public ?string $name,
        public ?string $sku,
        public ?Unit $unit,
        /** What the despatch sent out. */
        public string $sold,
        /** What every OTHER return has already brought back on this line. */
        public string $returned,
        /** `sold − returned`, floored at zero — the ceiling for this row. */
        public string $remaining,
        /** What THIS return already holds for the line. Empty while creating one. */
        public string $quantity,
        public string $unit_price,
        public DiscountType $discount_type,
        public string $discount_value,
        public bool $taxable,
    ) {}

    /**
     * @param  string  $returned  from {@see ReturnedQuantities::forSalesOrderItems()}, already
     *                            excluding the return being edited
     * @param  string  $quantity  what this return holds for the line, or `''`
     */
    public static function fromOrderItem(SalesOrderItem $line, string $returned, string $quantity): self
    {
        // Loaded withTrashed by the relation, so a product archived after the despatch still
        // names itself. A hard delete nulls the FK and leaves the money intact.
        $product = $line->product;

        return new self(
            sales_order_item_id: $line->id,
            name: $product?->name,
            sku: $product?->sku,
            unit: $product?->unit,
            // Trimmed, all of them: every one of these is read beside an input or put into one,
            // and `12.0000` in a column of quantities is four digits of noise per row.
            sold: Decimals::trim($line->quantity),
            returned: Decimals::trim($returned),
            remaining: Decimals::trim(ReturnedQuantities::remaining($line->quantity, $returned)),
            quantity: $quantity === '' ? '' : Decimals::trim($quantity),
            unit_price: Decimals::trim($line->unit_price),
            discount_type: $line->discount_type,
            discount_value: Decimals::trim($line->discount_value),
            taxable: $line->taxable,
        );
    }
}
