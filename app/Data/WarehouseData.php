<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Warehouse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One warehouse, as the listing sends it.
 *
 * **One stock number, not v1's three.** v1 sends `items_in_stock`, `low_stock` and
 * `out_of_stock` on every row. Two of those answer the same question — an item is below
 * its level whether or not the shelf is empty — and the third says how much is in the
 * building, which is a fact about the building rather than something anybody scanning a
 * list is deciding on. What a list is for is finding the row that needs a person, so it
 * carries the one number that says so.
 *
 * `needs_reorder` is required rather than defaulted, deliberately. A default would make
 * a caller that forgot it ship a zero, which is a wrong number rather than an absent
 * one — and a warehouse quietly claiming nothing needs restocking is the worst way for
 * this to fail.
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
        /** Items at or below their reorder level here — see {@see WarehouseInventory}. */
        public int $needs_reorder,
    ) {}

    public static function fromWarehouse(Warehouse $warehouse, int $needsReorder): self
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
            needs_reorder: $needsReorder,
        );
    }
}
