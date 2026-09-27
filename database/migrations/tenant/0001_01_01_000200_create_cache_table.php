<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant cache. It holds this workspace's spatie/laravel-permission catalog, which
 * App\Tenancy\PermissionCacheTenancyBootstrapper points here — left alone, spatie keeps
 * the store it built before tenancy started, and that is the central one. The bootstrapper
 * also suffixes the key with the tenant id, so a switch to redis/memcached (one shared
 * store, no tenant database to point at) cannot leak roles across tenants.
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
