<?php

declare(strict_types=1);

namespace App\Data;

use App\Actions\FulfillSalesOrder;
use App\Enums\StockItemType;
use App\Enums\Unit;
use App\Models\Product;
use App\Services\WarehouseInventory;
use App\Support\Decimals;
use App\Support\OrderAvailability;
use App\Support\StockItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One product an order needs, against what the chosen warehouse actually holds.
 *
 * **Per product, not per line.** Nothing stops a sales order carrying the same product on two
 * lines at two prices — the migration says why there is no unique index — and the question
 * "can this be shipped" is only answerable once those two lines are added together. Two rows
 * of five against eight on the shelf is a shortfall, and a per-line reading passes both.
 * {@see OrderAvailability::required()} is where that addition happens, once, for both the
 * screen and {@see FulfillSalesOrder}.
 *
 * **`short` is decided here rather than in the browser**, for the reason
 * {@see WarehouseInventory} computes `needs_reorder` in SQL: the panel's warning
 * and the Action's refusal are the same question, and a JavaScript `<` on two decimal strings
 * is a second answer to it that would disagree the first time a quantity had four places.
 *
 * **Every figure is display-shaped.** `required` and `on_hand` are trimmed rather than left at
 * `decimal(15,4)`'s full scale — `8.0000` beside `5.0000` is eight digits of noise on a row
 * whose whole job is to be glanced at. The arithmetic already happened; these are its report.
 *
 * Only products that still exist get a row. A line whose product was hard-deleted issues
 * nothing — {@see FulfillSalesOrder} skips it, exactly as `ReceivePurchaseOrder` skips a
 * deleted material — so there is no availability question to ask about it, and inventing a
 * row would put a warning next to something nobody can act on.
 */
#[TypeScript]
final class StockAvailabilityData extends Data
{
    /** The scale of `decimal(15,4)` — {@see OrderAvailability} adds up at the same one. */
    private const SCALE = 4;

    public function __construct(
        /** The picker key, `product:5` — {@see StockItem}'s encoding, so it is a stable id. */
        public string $item,
        public string $name,
        public string $sku,
        public Unit $unit,
        /** What the whole order needs of this product, every line added together. */
        public string $required,
        /** What the chosen warehouse held when the page was drawn. Never a reservation. */
        public string $on_hand,
        public bool $short,
    ) {}

    /**
     * @param  numeric-string  $required
     * @param  numeric-string  $onHand
     */
    public static function for(Product $product, string $required, string $onHand): self
    {
        return new self(
            item: StockItem::key(StockItemType::Product->value, $product->id),
            name: $product->name,
            sku: $product->sku,
            unit: $product->unit,
            required: Decimals::trim($required),
            on_hand: Decimals::trim($onHand),
            // At the column's own scale, and `<` rather than `<=`: shipping the last one you
            // have is an ordinary day's work, not a shortfall.
            short: bccomp($onHand, $required, self::SCALE) < 0,
        );
    }
}
