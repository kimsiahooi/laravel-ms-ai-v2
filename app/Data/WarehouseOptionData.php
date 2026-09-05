<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Warehouse;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One warehouse in a picker, with the site it stands on.
 *
 * Not {@see OptionData}, because a warehouse's name does not identify it: two sites
 * with a "Main store" are ordinary, and a picker offering "Main store" twice is a
 * picker you cannot use. The site travels as its own field rather than joined into the
 * name — v1 shipped `"KL HQ · Main Store"`, which is a separator chosen on the server
 * for a screen it cannot see, in a language it had to pick.
 */
#[TypeScript]
final class WarehouseOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $site,
    ) {}

    public static function fromWarehouse(Warehouse $warehouse): self
    {
        return new self(
            id: $warehouse->id,
            name: $warehouse->name,
            site: $warehouse->location->name,
        );
    }
}
