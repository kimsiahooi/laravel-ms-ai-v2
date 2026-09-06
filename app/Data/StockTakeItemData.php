<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockItemType;
use App\Enums\Unit;
use App\Models\StockTakeItem;
use App\Services\StockService;
use App\Support\Decimals;
use App\Support\StockItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a count sheet.
 *
 * **`counted_quantity` stays null when nobody has reached that shelf, and the client
 * needs that null.** "Not counted" and "counted, and there were none" are different
 * answers that a sheet has to show differently — one is a dash, the other is a zero with
 * a difference beside it — and v1 could not tell them apart because it seeded every line
 * with its expected quantity. Sending `'0'` here would put that bug back in a place no
 * type checker could see it.
 *
 * The three quantities are decimal strings, trimmed for reading: the columns always
 * return `40.5000`, and four digits of trailing zeros on every row of a five-hundred-line
 * sheet say nothing except how the column was declared. Strings rather than numbers for
 * the reason {@see StockService} gives — a quantity that has been through a float is a
 * quantity that may no longer be what was typed.
 *
 * **Values, not sentences**, like {@see StockMovementData}: `type` and `unit` are enum
 * cases the browser looks up in `lang/`.
 *
 * `system_quantity` is what the system believed when the line joined the sheet, and it
 * is shown so the counter has something to confirm. `applied_delta` is what posting
 * actually moved, and it is null until then — the sheet shows a difference it works out
 * itself while the take is a draft, and this one once there is a real answer.
 */
#[TypeScript]
final class StockTakeItemData extends Data
{
    public function __construct(
        public int $id,
        /** The picker key, `product:5` — the same encoding {@see StockItem} uses. */
        public string $item,
        /** Null once the product or material has been force-deleted from the catalog. */
        public ?string $name,
        public ?string $sku,
        public StockItemType $type,
        /**
         * Null in exactly the same case as `name` and `sku`, and only that case.
         *
         * The unit is a fact about the catalogue row, not about the count, so it goes
         * when the row does. The spec asked for a non-nullable `Unit` alongside a
         * nullable name, which cannot both be true of a force-deleted item — and
         * inventing a unit to keep the type simple would put a wrong word next to a
         * real quantity.
         */
        public ?Unit $unit,
        public string $system_quantity,
        /** Null means not counted yet — never "counted zero". See the class. */
        public ?string $counted_quantity,
        /** What posting moved. Null until the take is posted. */
        public ?string $applied_delta,
    ) {}

    public static function fromStockTakeItem(StockTakeItem $item): self
    {
        // Loaded withTrashed by the relation, so an item archived mid-count still names
        // itself. A force-delete does take the row, and a line that can no longer say
        // what it counted is still a line — the sheet shows a dash rather than breaking.
        $stockable = $item->stockable;

        return new self(
            id: $item->id,
            // Built from the morph columns rather than from the model, so the key is
            // there whether or not the catalogue row still is — it is what the "add a
            // found item" dialog compares against to refuse a duplicate.
            item: StockItem::key($item->stockable_type, $item->stockable_id),
            name: $stockable?->name,
            sku: $stockable?->sku,
            type: StockItemType::from($item->stockable_type),
            unit: $stockable?->unit,
            system_quantity: Decimals::trim((string) $item->system_quantity),
            counted_quantity: $item->counted_quantity === null
                ? null
                : Decimals::trim((string) $item->counted_quantity),
            applied_delta: $item->applied_delta === null
                ? null
                : Decimals::trim((string) $item->applied_delta),
        );
    }
}
