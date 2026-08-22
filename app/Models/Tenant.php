<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Events;

/**
 * A customer workspace, with its own database.
 *
 * @property string $id
 * @property string $name
 * @property string $locale
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    // The base Tenant does not ship database management; multi-DB mode needs this.
    use HasDatabase;
    use SoftDeletes;

    /**
     * The `id` is a slug-style string key ("acme") supplied at creation, and it
     * doubles as the tenant database name suffix (prefix + id). stancl's base model
     * already keys on `id`, so no getTenantKeyName() override is needed — but with a
     * null id_generator, GeneratesIds would treat the key as auto-incrementing and
     * clobber it with lastInsertId. Hence the two overrides below.
     */
    protected $keyType = 'string';

    /**
     * Remap of stancl's base $dispatchesEvents.
     *
     * Database teardown (TenantDeleted -> DeleteDatabase, wired in
     * TenancyServiceProvider) must fire on a FORCE delete, never on the soft-delete
     * `deleted` event — otherwise soft-deleting a tenant would drop its database and
     * make restore impossible.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\UpdatingTenant::class,
        'updated' => Events\TenantUpdated::class,
        'deleting' => Events\DeletingTenant::class,
        // 'deleted' intentionally NOT mapped to TenantDeleted (see docblock).
        'forceDeleted' => Events\TenantDeleted::class,
    ];

    public function getIncrementing(): bool
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    /**
     * Attributes kept as real `tenants` columns. Every other attribute overflows
     * into the json `data` column. The primary key and `deleted_at` MUST be listed
     * so they write to real columns.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'locale',
            'deleted_at',
        ];
    }
}
