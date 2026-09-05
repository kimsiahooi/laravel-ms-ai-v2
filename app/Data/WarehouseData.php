<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Warehouse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One warehouse, as the listing sends it.
 *
 * No stock counts yet. v1's carries `items_in_stock` / `low_stock` / `out_of_stock`,
 * computed from a UNION over products and raw materials joined to `warehouse_stocks`
 * and `warehouse_reorder_levels` — neither table exists here, and shipping the fields
 * as zeroes would put three numbers on every row that are wrong rather than absent.
 * They arrive with StockService.
 */
#[TypeScript]
final class WarehouseData extends Data
{
    public function __construct(
        public int $id,
        /** The site's id — what the edit form's picker preselects. */
        public int $location_id,
        /** And its name, which is what the row shows. */
        public string $location,
        public string $name,
        public ?string $code,
        public ?string $address,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromWarehouse(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            location_id: $warehouse->location_id,
            // Not nullable, unlike a product's category: the FK is NOT NULL and
            // restricted, and the relation is withTrashed, so there is always a name.
            location: $warehouse->location->name,
            name: $warehouse->name,
            code: $warehouse->code,
            address: $warehouse->address,
            created_at: $warehouse->created_at->toIso8601String(),
            creator: $warehouse->creator?->name,
        );
    }
}
