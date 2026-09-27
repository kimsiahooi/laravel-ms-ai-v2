<?php

declare(strict_types=1);

namespace App\Tenancy;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Gives spatie/laravel-permission a tenant-specific cache key and a clean in-memory catalog
 * for each workspace.
 *
 * It is the two packages' documented pieces and nothing else:
 *
 * - stancl's documented SpatiePermissionsBootstrapper: suffix the registrar's `cacheKey`
 *   with the tenant key while tenancy is active.
 * - spatie's documented call for a tenant switch, `initializeCache()`: it re-reads the key
 *   and the store from config and drops the catalog already loaded in memory, so a
 *   long-running process (queue worker, a `tenants:run` loop) never answers one
 *   workspace's gate checks with another's permissions. On revert it also restores the
 *   unsuffixed key.
 *
 * WHERE the catalog is stored is not this class's job. {@see DatabaseCacheBootstrapper}
 * moves the whole `database` cache store — the one object the registrar holds — into the
 * workspace's own database, so this class has no ordering constraint. While each workspace
 * has its own database the suffix is redundant; it stays because it is what stancl
 * documents and because it is the only isolation left if the cache ever moves to a shared
 * driver (redis, memcached).
 *
 * A correction to this class's history: its first version also re-pointed the store by
 * changing config and PURGING it from the cache manager, describing that as stancl v4's
 * mechanism. v4 never purges — it re-points the built store in place — and the purge was
 * the reason the rate limiter, which holds the boot-time store object, stayed on central.
 *
 * `cache:clear` and a bare `permission:cache-reset` reach the central database only. A
 * workspace's catalog is cleared with `tenants:run permission:cache-reset`, which the
 * deploy script runs after migrating.
 */
final class PermissionCacheTenancyBootstrapper implements TenancyBootstrapper
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->registrar->initializeCache();
        $this->registrar->cacheKey .= '.tenant.'.$tenant->getTenantKey();
    }

    public function revert(): void
    {
        $this->registrar->initializeCache();
    }
}
