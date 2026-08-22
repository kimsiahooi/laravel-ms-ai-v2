<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One archived workspace. Deliberately a second class rather than nullable fields on
 * {@see TenantData}.
 *
 * The two listings genuinely send different shapes — the live one dates from creation,
 * the archive from when it was archived — and a single class carrying both would have
 * to make each nullable. That would ship an always-null `deleted_at` to the live list,
 * an unread `created_at` to the archive, and it would widen the column accessor's type
 * from `string` to `string | null` on both screens to describe a case that cannot
 * happen. Two small classes say what is actually true.
 */
#[TypeScript]
final class ArchivedTenantData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $deleted_at,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        return new self(
            slug: $tenant->getKey(),
            name: $tenant->name,
            // Nullable because the model's column is, even though `onlyTrashed()`
            // guarantees a value here. Asserting non-null would buy a non-null type on
            // the client in exchange for a 500 the day something else lists a tenant.
            deleted_at: $tenant->deleted_at?->toIso8601String(),
        );
    }
}
