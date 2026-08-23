<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Unit;
use App\Models\RawMaterial;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One raw material, as the listing sends it.
 *
 * Every field travels, because the edit dialog is opened from a row and seeds itself
 * from exactly this object — fetching the rest would be a round trip for data already
 * on the page.
 *
 * `unit` stays a {@see Unit} rather than being flattened to a string, so the transformer
 * emits `App.Enums.Unit` and the browser cannot render a unit the server does not know.
 *
 * v1's version also carried a `purchase_history` collection: which received purchase
 * orders this material came in on. That is not missing so much as not yet possible —
 * purchase orders arrive in phase 5. It returns with them.
 */
#[TypeScript]
final class RawMaterialData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public Unit $unit,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromRawMaterial(RawMaterial $rawMaterial): self
    {
        return new self(
            id: $rawMaterial->id,
            name: $rawMaterial->name,
            sku: $rawMaterial->sku,
            barcode: $rawMaterial->barcode,
            unit: $rawMaterial->unit,
            created_at: $rawMaterial->created_at->toIso8601String(),
            creator: $rawMaterial->creator?->name,
        );
    }
}
