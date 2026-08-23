<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Unit;
use App\Models\Product;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One product, as the listing sends it.
 *
 * Both the id and the resolved name travel for the category and the supplier. They
 * answer two different questions and neither substitutes for the other: the table shows
 * the name, and the edit dialog needs the id to seed its picker. Sending only the id
 * would mean a second request to render a row; only the name would mean guessing an id
 * back from a string that is not unique.
 */
#[TypeScript]
final class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sku,
        public ?string $barcode,
        public ?string $description,
        public ?int $category_id,
        public ?string $category,
        public ?int $supplier_id,
        public ?string $supplier,
        public Unit $unit,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromProduct(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            sku: $product->sku,
            barcode: $product->barcode,
            description: $product->description,
            category_id: $product->category_id,
            category: $product->category?->name,
            supplier_id: $product->supplier_id,
            supplier: $product->supplier?->name,
            unit: $product->unit,
            created_at: $product->created_at->toIso8601String(),
            creator: $product->creator?->name,
        );
    }
}
