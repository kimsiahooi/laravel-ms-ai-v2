<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Bus\PendingDispatch;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
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
    use Searchable;
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

    /**
     * A workspace is findable by what it is called and by the address it answers on —
     * `id` IS the slug, so it is the one people read off a URL and paste back.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['id', 'name'];
    }

    public function getIncrementing(): bool
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    /**
     * Runs the callback inside this workspace and ALWAYS switches back afterwards —
     * including when the callback throws.
     *
     * stancl v3's version (the TenantRun trait) switches back only on success: a throw
     * skips its trailing tenancy()->end(), and the rest of the request runs inside this
     * workspace — its database, its cache, its permission key. ProvisionTenant then rolls
     * the workspace back while still inside it. This is v4's Tenancy::run() backported
     * as-is (try/finally, and a returned PendingDispatch dropped before the switch-back so
     * it cannot dispatch from its destructor without the tenant stamped on it). Delete it
     * on upgrading to v4, which ships exactly this.
     *
     * `callable`, not v4's `Closure`: narrowing the parameter would break v3's contract.
     * Like v4, it does not cover runForMultiple() or central(); nothing here calls those
     * mid-request.
     */
    public function run(callable $callback): mixed
    {
        $original = tenancy()->tenant;
        $result = null;

        try {
            tenancy()->initialize($this);
            $result = $callback($this);
        } finally {
            if ($result instanceof PendingDispatch) {
                $result = null;
            }

            $original instanceof TenantContract
                ? tenancy()->initialize($original)
                : tenancy()->end();
        }

        return $result;
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
