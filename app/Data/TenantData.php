<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One live workspace, as the console listing sends it.
 *
 * Property names are snake_case on purpose: they are the wire, and matching the JSON
 * exactly means the generated TypeScript needs no mapping layer and no renaming pass
 * in the pages that read it.
 *
 * `slug` is the one name that is not the model's. A workspace's primary key IS its
 * slug — see {@see Tenant} — so `id` is what a naive `::from($tenant)` would emit, and
 * "id" is precisely the wrong word for something that appears in every URL. Hence the
 * named factory below: the rename happens once, here, rather than in every consumer.
 */
#[TypeScript]
final class TenantData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $created_at,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        return new self(
            slug: $tenant->getKey(),
            name: $tenant->name,
            // ISO 8601 at the boundary rather than a Carbon left for the transformer to
            // guess at: the client formats relative times itself and only ever needs a
            // string it can parse deterministically.
            created_at: $tenant->created_at->toIso8601String(),
        );
    }
}
