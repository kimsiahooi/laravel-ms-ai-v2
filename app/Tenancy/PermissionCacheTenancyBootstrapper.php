<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Http\Middleware\InitializeTenancyFromPath;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use LogicException;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Keeps spatie/laravel-permission's cache inside the workspace: in the tenant database's own
 * `cache` table, under a tenant-suffixed key.
 *
 * Why changing the key alone was not enough
 * -----------------------------------------
 * The registrar is a singleton that resolves its cache store ONCE, and a `database` store
 * holds on to whichever connection was the default at the moment it was built. stancl
 * instantiates every bootstrapper before running any of them, so injecting the registrar
 * here builds it — store and all — while the default is still `central`. Swapping only the
 * key (the pattern stancl's v3 docs show) therefore wrote every workspace's catalog into the
 * CENTRAL `cache` table: separated by key, never by database. It is the same class of bug
 * as the session one described on {@see InitializeTenancyFromPath}.
 *
 * What this does instead, and where it comes from
 * -----------------------------------------------
 * Both halves are the packages' own documented answers, not an invention:
 *
 * - spatie: when a request switches tenant, change the cache configuration and call
 *   `initializeCache()`, which re-reads the store and the key and drops the in-memory
 *   catalog (docs: advanced-usage/cache).
 * - stancl v4's DatabaseCacheBootstrapper: point the `database` cache store's `connection`
 *   and `lock_connection` at `tenant`, purge it so the manager builds it again, and restore
 *   both on revert. On an upgrade to v4 that bootstrapper can take over this half.
 *
 * The purge has to reach the manager the REGISTRAR holds, which is why the manager is
 * injected here rather than fetched in bootstrap(): both are resolved together, outside
 * tenancy, before stancl's CacheTenancyBootstrapper swaps the container's `cache` binding.
 *
 * It must run after {@see DatabaseTenancyBootstrapper} — the `tenant` connection has to
 * exist — and it says so loudly rather than quietly caching into central again.
 *
 * Only a `database` store is re-pointed. Any other driver (redis, memcached) has no
 * `tenant` connection to point at; there the key suffix below is the only isolation, which
 * is also why the suffix stays even though the tenant's own table already separates it:
 * spatie asks the manager for a store directly, bypassing stancl's cache tags.
 *
 * Two consequences worth knowing
 * ------------------------------
 * The cache now shares the tenant connection, so a forget or a rebuild inside a
 * `DB::transaction()` commits or rolls back with the rows it describes. The cost is a row
 * lock on the key until commit — do not forget the cache early in a long transaction.
 *
 * `cache:clear`, `optimize:clear` and a bare `permission:cache-reset` reach the central
 * database only. Clearing a workspace's permissions is `tenants:run permission:cache-reset`,
 * which the deploy script runs after migrating.
 */
final class PermissionCacheTenancyBootstrapper implements TenancyBootstrapper
{
    /** The `database` store spatie caches in, or null when it uses another driver. */
    private readonly ?string $store;

    private readonly ?string $originalConnection;

    private readonly ?string $originalLockConnection;

    public function __construct(
        private readonly PermissionRegistrar $registrar,
        private readonly CacheManager $cache,
        private readonly Repository $config,
    ) {
        // spatie's `default` means the application's default store.
        $store = $config->get('permission.cache.store');
        $store = $store === 'default' ? $config->get('cache.default') : $store;

        $this->store = is_string($store) && $config->get("cache.stores.{$store}.driver") === 'database'
            ? $store
            : null;

        [$connection, $lockConnection] = $this->store === null ? [null, null] : [
            $config->get("cache.stores.{$this->store}.connection"),
            $config->get("cache.stores.{$this->store}.lock_connection"),
        ];

        $this->originalConnection = is_string($connection) ? $connection : null;
        $this->originalLockConnection = is_string($lockConnection) ? $lockConnection : null;
    }

    public function bootstrap(Tenant $tenant): void
    {
        if ($this->store !== null && $this->config->get('database.connections.tenant') === null) {
            throw new LogicException(self::class.' must run after '.DatabaseTenancyBootstrapper::class.'.');
        }

        $this->pointStoreAt('tenant', 'tenant');

        $this->registrar->cacheKey .= '.tenant.'.$tenant->getTenantKey();
    }

    public function revert(): void
    {
        $this->pointStoreAt($this->originalConnection, $this->originalLockConnection);
    }

    /**
     * Re-points the store and has the registrar pick it up. initializeCache() also resets
     * the key from config, so revert() needs nothing else to restore it.
     */
    private function pointStoreAt(?string $connection, ?string $lockConnection): void
    {
        if ($this->store !== null) {
            $this->config->set([
                "cache.stores.{$this->store}.connection" => $connection,
                "cache.stores.{$this->store}.lock_connection" => $lockConnection,
            ]);

            $this->cache->purge($this->store);
        }

        $this->registrar->initializeCache();
    }
}
