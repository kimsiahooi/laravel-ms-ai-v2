<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant cache. While the workspace is active it holds everything the `database` cache
 * store holds — rate-limit counters, spatie/laravel-permission's catalog, Fortify's
 * two-factor replay guard, any `Cache::` call — because App\Tenancy\DatabaseCacheBootstrapper
 * re-points that store here. Left alone, those services keep the store they took at boot,
 * which is the central one. App\Tenancy\PermissionCacheTenancyBootstrapper also suffixes
 * spatie's key with the tenant id, so a switch to redis/memcached (one shared store, no
 * tenant database to point at) cannot leak roles across tenants.
 *
 * The `expiration` columns were widened to BIGINT later — see
 * 2026_09_27_000000_widen_cache_expiration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
