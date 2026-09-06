<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockItemType;
use App\Enums\Unit;
use App\Services\WarehouseInventory;
use App\Support\Decimals;
use App\Support\StockItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One catalogue item seen from inside one warehouse: what is there, and the level at
 * which somebody wants to know about it.
 *
 * Built from a raw row rather than a model, because there is no model to build it from
 * — the row is one leg of the UNION in {@see WarehouseInventory}, which is the only way
 * to ask a two-table catalogue a single paginated question. `::from($row)` would not do:
 * the union carries strings for everything and has no opinion about `needs_reorder`.
 *
 * **Values, not sentences**, like {@see StockMovementData}: `type` and `unit` are enum
 * cases the browser translates. v1 sent `"Raw material"` from the server, which is a
 * label that can only ever be English.
 *
 * `needs_reorder` is the one field this does not work out. It is decided in SQL, so the
 * badge on a row, the summary above the list and the `attention` filter cannot drift
 * apart — see {@see WarehouseInventory}.
 *
 * **`min_stock` is nullable and that is the whole design.** No row in
 * `warehouse_reorder_levels` means no threshold, and null is what no threshold looks
 * like — distinct from `'0'`, which is not stored at all. A screen showing `0` for an
 * item nobody has an opinion about is claiming a decision was taken.
 */
#[TypeScript]
final class WarehouseItemData extends Data
{
    public function __construct(
        /** The encoded picker value, e.g. `product:5` — see {@see StockItem}. */
        public string $item,
        public string $name,
        public string $sku,
        public StockItemType $type,
        public Unit $unit,
        /** On hand in THIS warehouse. `'0'` when the item has never been stocked here. */
        public string $on_hand,
        /** THIS warehouse's threshold, or null when none is set. */
        public ?string $min_stock,
        /** On hand has reached the threshold. False whenever there is no threshold. */
        public bool $needs_reorder,
    ) {}

    /**
     * One row of the UNION in {@see WarehouseInventory}.
     *
     * The numeric fields are typed loosely on purpose. These are `decimal(15,4)`
     * columns, one wrapped in `COALESCE` and one in a `CASE`, and whether each arrives
     * as a string or a number is the driver's business — this is the boundary that
     * settles it.
     *
     * @param  object{type: string, id: int|string, name: string, sku: string, unit: string, on_hand: string|int|float, min_stock: string|int|float|null, needs_reorder: int|string|bool}  $row
     */
    public static function fromRow(object $row): self
    {
        return new self(
            item: StockItem::key($row->type, (int) $row->id),
            name: $row->name,
            sku: $row->sku,
            type: StockItemType::from($row->type),
            unit: Unit::from($row->unit),
            // Trimmed for reading — the columns always return 40.5000. Same treatment,
            // for the same reason, as the ledger's quantity.
            on_hand: Decimals::trim((string) $row->on_hand),
            min_stock: $row->min_stock === null ? null : Decimals::trim((string) $row->min_stock),
            // Decided in SQL, not here. Comparing the two decimals in PHP would be a
            // second definition of the same rule, and the summary above the list and
            // the `attention` filter both read the first one — see WarehouseInventory.
            needs_reorder: (bool) $row->needs_reorder,
        );
    }
}
