<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use LogicException;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Puts the whole `database` cache store inside the active workspace's own database, and
 * back on central when tenancy ends.
 *
 * Why the store has to move, not just its keys
 * --------------------------------------------
 * Several services take the cache store once, at boot, and keep it: Laravel's RateLimiter
 * (every throttle counter — Fortify's login, two-factor, passkeys and verification limits,
 * and `throttle:6,1` on the password change), spatie's PermissionRegistrar, Fortify's
 * two-factor replay guard. None of their keys names a workspace — login is `email|ip`,
 * two-factor is the user id, and every workspace's first administrator is user #1 — so
 * while the store stayed on central, a lock-out in one workspace locked the same email or
 * user id out of every other one.
 *
 * All of those services hold ONE store object: the one the cache manager built at boot.
 * So this re-points that object IN PLACE — setConnection()/setLockConnection() — and every
 * holder follows. It never purges the store: a purge hands later callers a new object while
 * everything that captured the old one at boot stays on central (the first version of
 * PermissionCacheTenancyBootstrapper did exactly that, and the rate limiter never moved).
 *
 * Where this comes from
 * ---------------------
 * It is stancl v4's DatabaseCacheBootstrapper backported, which v4's docs recommend in place
 * of CacheTenancyBootstrapper when the cache store is `database`: remember each
 * database-driver store's connection and lock connection, point both — in config and on the
 * built store — at `tenant`, restore both on revert. Two parts are left out: the `$stores`
 * knob (this app has one database-driver store and scopes all of them, v4's default) and
 * the GlobalCache adjustment (v3 has no hook for it, and nothing here uses GlobalCache). On
 * upgrading to v4, replace this class with v4's.
 *
 * It must run directly after {@see DatabaseTenancyBootstrapper} — the `tenant` connection
 * has to exist — and throws rather than quietly caching into central.
 *
 * Consequences
 * ------------
 * Inside a workspace `Cache::get()/put()` work and are that workspace's own; outside one
 * (`/admin`, artisan) the store is central. `cache:clear` and `optimize:clear` reach central
 * only — a workspace's cache is cleared with `tenants:run cache:clear --tenants=<slug>`. The
 * store shares the tenant connection, so a cache write inside `DB::transaction()` commits or
 * rolls back with it. Anything that must stay global across workspaces must not use the
 * `database` store.
 */
final class DatabaseCacheBootstrapper implements TenancyBootstrapper
{
    /** @var array<string, string> Store name => its connection before tenancy. */
    private array $originalConnections = [];

    /** @var array<string, string> Store name => its lock connection before tenancy. */
    private array $originalLockConnections = [];

    public function __construct(
        private readonly Repository $config,
        private readonly CacheManager $cache,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        if ($this->config->get('database.connections.tenant') === null) {
            throw new LogicException(self::class.' must run after '.DatabaseTenancyBootstrapper::class.'.');
        }

        $central = $this->centralConnection();

        foreach ($this->databaseStores() as $name) {
            $connection = $this->config->get("cache.stores.{$name}.connection");
            $lockConnection = $this->config->get("cache.stores.{$name}.lock_connection");

            $this->originalConnections[$name] = is_string($connection) ? $connection : $central;
            $this->originalLockConnections[$name] = is_string($lockConnection) ? $lockConnection : $central;

            $this->pointAt($name, 'tenant', 'tenant');
        }
    }

    public function revert(): void
    {
        foreach ($this->originalConnections as $name => $connection) {
            $this->pointAt($name, $connection, $this->originalLockConnections[$name]);
        }
    }

    /**
     * Points a store at a connection twice over: in config, for a store built later, and on
     * the store object already built, for everything that captured it at boot.
     */
    private function pointAt(string $name, string $connection, string $lockConnection): void
    {
        $this->config->set([
            "cache.stores.{$name}.connection" => $connection,
            "cache.stores.{$name}.lock_connection" => $lockConnection,
        ]);

        $store = $this->cache->store($name)->getStore();

        if (! $store instanceof DatabaseStore) {
            throw new LogicException("Cache store [{$name}] is configured as `database` but is not a DatabaseStore.");
        }

        $store->setConnection(DB::connection($connection));
        $store->setLockConnection(DB::connection($lockConnection));
    }

    /**
     * Every configured cache store whose driver is `database`.
     *
     * @return list<string>
     */
    private function databaseStores(): array
    {
        $stores = $this->config->get('cache.stores', []);
        $names = [];

        foreach (is_array($stores) ? $stores : [] as $name => $store) {
            if (is_string($name) && is_array($store) && ($store['driver'] ?? null) === 'database') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function centralConnection(): string
    {
        $central = $this->config->get('tenancy.database.central_connection');

        return is_string($central) ? $central : 'central';
    }
}
