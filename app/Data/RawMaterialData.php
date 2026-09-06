<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Unit;
use App\Models\RawMaterial;
use App\Support\Decimals;
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
    /** How many product names a row carries before the rest become a count. */
    private const BOM_PRODUCTS_SHOWN = 5;

    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public Unit $unit,
        /**
         * What this normally costs, trimmed — `12.5`, not `12.5000` — or null if
         * nobody has said.
         *
         * Trimmed rather than rounded to the currency's two places, and that matters:
         * the column holds four, a material priced per gram legitimately uses them,
         * and seeding the edit box with a rounded figure would quietly write the
         * rounding back on the next save. Exactly what is stored goes out.
         */
        public ?string $default_cost,
        /**
         * Names of the products whose bill of materials calls for this — capped, so a
         * material used by two hundred products does not put two hundred strings in
         * every row of the listing. `bom_product_count` carries the real total.
         *
         * Here so the screen can say *why* a material cannot be deleted before anyone
         * presses Delete, rather than only after. The controller refuses it either way;
         * this is the half that stops the button being a trap.
         *
         * @var list<string>
         */
        public array $bom_products,
        public int $bom_product_count,
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
            default_cost: self::amount($rawMaterial->default_cost),
            bom_products: array_values($rawMaterial->products->take(self::BOM_PRODUCTS_SHOWN)->pluck('name')->all()),
            bom_product_count: $rawMaterial->products->count(),
            created_at: $rawMaterial->created_at->toIso8601String(),
            creator: $rawMaterial->creator?->name,
        );
    }

    /**
     * A stored `decimal(15,4)` as the form and the listing both want it, or null.
     *
     * The same shape {@see StockItemOptionData} sends the picker's suggestion in, so
     * the number on this row and the number that prefills a purchase order line are
     * one string rather than two roundings of it.
     */
    private static function amount(?string $stored): ?string
    {
        return $stored === null ? null : Decimals::trim($stored);
    }
}
