<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Category;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One category, as the listing sends it.
 *
 * snake_case property names are the wire format — matching the JSON exactly is what
 * lets the generated TypeScript be read straight off the page props with no renaming
 * pass in between.
 *
 * A named factory rather than a bare `::from($category)`: `creator` here is a person's
 * name, while on the model it is a relation. Flattening it once, at the boundary,
 * keeps every consumer from having to know that.
 */
#[TypeScript]
final class CategoryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromCategory(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            description: $category->description,
            // ISO 8601 at the boundary. The client formats dates itself and only ever
            // needs a string both renders parse the same way — see lib/format.ts on
            // why that matters under SSR.
            created_at: $category->created_at->toIso8601String(),
            // Null for a seeded row, and null again once the author is force-deleted.
            creator: $category->creator?->name,
        );
    }
}
