<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Location;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One site, as the listing sends it.
 *
 * snake_case property names are the wire format — matching the JSON exactly is what
 * lets the generated TypeScript be read straight off the page props with no renaming
 * pass in between.
 *
 * A named factory rather than a bare `::from($location)`: `creator` here is a person's
 * name, while on the model it is a relation. Flattening it once, at the boundary,
 * keeps every consumer from having to know that.
 */
#[TypeScript]
final class LocationData extends Data
{
    /**
     * How many warehouse names travel with a row. Enough to make the refusal concrete
     * — a name is what someone recognises — without turning a listing into a payload
     * that scales with somebody else's estate.
     */
    private const WAREHOUSES_SHOWN = 5;

    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $address,
        /**
         * Names of the warehouses standing on this site — capped, so a site with two
         * hundred does not put two hundred strings in every row of the listing.
         * `warehouse_count` carries the real total.
         *
         * Here so the screen can say *why* a site cannot be deleted before anyone
         * presses Delete, rather than only after. The controller refuses it either
         * way; this is the half that stops the button being a trap.
         *
         * @var list<string>
         */
        public array $warehouses,
        public int $warehouse_count,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromLocation(Location $location): self
    {
        return new self(
            id: $location->id,
            name: $location->name,
            code: $location->code,
            address: $location->address,
            warehouses: array_values($location->warehouses->take(self::WAREHOUSES_SHOWN)->pluck('name')->all()),
            warehouse_count: $location->warehouses->count(),
            // ISO 8601 at the boundary. The client formats dates itself and only ever
            // needs a string both renders parse the same way — see lib/format.ts on
            // why that matters under SSR.
            created_at: $location->created_at->toIso8601String(),
            // Null for a seeded row, and null again once the author is force-deleted.
            creator: $location->creator?->name,
        );
    }
}
