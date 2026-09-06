<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DiscountType;
use App\Enums\StockItemType;
use App\Enums\Unit;
use App\Models\PurchaseOrderItem;
use App\Services\StockService;
use App\Support\Decimals;
use App\Support\Money;
use App\Support\StockItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a purchase order — what was ordered, and what was agreed for it.
 *
 * Read by two screens with opposite needs, which is what shapes the field types. The
 * detail page shows the line as a record; the form seeds an editable row from it. So the
 * numbers a person types back — the quantity, the cost, the discount — travel trimmed, in
 * the same string the input will hold, and the one number the server computed travels
 * rounded like money.
 *
 * **Decimal strings, never floats.** v1's DTO declared `float $quantity` and
 * `float $unit_cost` two lines after casting both to `decimal:4` on the model, throwing
 * away the reason the columns are fixed-point — see {@see StockService} on why the whole
 * engine works in strings.
 *
 * **Values, not sentences**, like {@see StockMovementData}: `discount_type` and `unit` are
 * enum cases the browser looks up in `lang/`.
 *
 * **No snapshot fields.** v1 carried a JSON blob of the material's name, sku and unit
 * written at order time; the migration says why that went. The identity here is read
 * through the material itself, on a relation that includes archived rows.
 */
#[TypeScript]
final class PurchaseOrderItemData extends Data
{
    public function __construct(
        public int $id,
        /**
         * The picker key, `raw_material:5` — the same encoding {@see StockItem} uses, so
         * the form's material picker and the stock pickers elsewhere speak one language.
         *
         * Empty once the material has been hard-deleted, which is the honest answer:
         * there is nothing left to point at. It reads on the form as an unselected row,
         * so re-saving that order means picking a material that still exists — which is
         * the only correct outcome, since you cannot order what no longer has a record.
         */
        public string $item,
        /** Null in exactly the case `item` is empty, and only that case. */
        public ?string $name,
        public ?string $sku,
        /**
         * Null in the same one case as `name`.
         *
         * The unit is a fact about the catalogue row, not about the line, so it goes when
         * the row does. Inventing one to keep the type simple would put a wrong word next
         * to a real quantity.
         */
        public ?Unit $unit,
        public string $quantity,
        public string $unit_cost,
        public DiscountType $discount_type,
        /** A percentage or an amount, depending on `discount_type`. Zero for `none`. */
        public string $discount_value,
        public bool $taxable,
        /** What the line comes to after its discount — computed by the server, never sent to it. */
        public string $line_total,
    ) {}

    /**
     * @param  string  $currency  the order's, because a line has no currency of its own —
     *                            it is one row of a document that is denominated once. It
     *                            is needed to round `line_total` to the places the
     *                            currency actually has, the same way
     *                            {@see PurchaseOrderData} rounds the order's four figures,
     *                            so the stored amount and the browser's running estimate
     *                            of it are the same string rather than the same number.
     */
    public static function fromPurchaseOrderItem(PurchaseOrderItem $item, string $currency): self
    {
        // Loaded withTrashed by the relation, so a material archived after the order was
        // raised still names itself on it. A hard delete nulls the FK, and a line that can
        // no longer say what it was for still says what was paid.
        $material = $item->rawMaterial;

        return new self(
            id: $item->id,
            // Built from the FK rather than from the model, so the key is there whether or
            // not the catalogue row still is — and empty, rather than pointing at nothing,
            // when it is not.
            item: $item->raw_material_id === null
                ? ''
                : StockItem::key(StockItemType::RawMaterial->value, $item->raw_material_id),
            name: $material?->name,
            sku: $material?->sku,
            unit: $material?->unit,
            // Trimmed rather than rounded: these three are what somebody typed and what
            // the form will put back in an input, and `12.0000` in a quantity box is four
            // digits of noise per row. A unit cost genuinely uses its fourth place —
            // 0.0125 of a pigment is a real price — so rounding it to the currency here
            // would destroy the number rather than tidy it.
            quantity: Decimals::trim((string) $item->quantity),
            unit_cost: Decimals::trim((string) $item->unit_cost),
            discount_type: $item->discount_type,
            discount_value: Decimals::trim((string) $item->discount_value),
            taxable: $item->taxable,
            // Money, and only ever displayed: rounded to what the currency can express,
            // for the reason the parameter note gives.
            line_total: Money::roundTo((string) $item->line_total, $currency),
        );
    }
}
