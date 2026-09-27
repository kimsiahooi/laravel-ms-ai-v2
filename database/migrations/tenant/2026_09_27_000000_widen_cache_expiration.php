<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the workspace cache tables in line with Laravel's own stub and the central tables:
 * `expiration` is a BIGINT with an index.
 *
 * The workspace tables were created with a plain INT and no index, which did not matter
 * while nothing but spatie's catalog lived here. Since DatabaseCacheBootstrapper moved the
 * whole `database` store into the workspace — rate-limit counters, and any `Cache::` call —
 * it does. `Cache::forever()` stores now + ten years, and that passes a signed INT's ceiling
 * for anything written after 2028-01-22; MySQL's strict mode then refuses the row. The
 * index is the stub's, for expiry lookups.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cache', 'cache_locks'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->bigInteger('expiration')->change();
                $table->index('expiration');
            });
        }
    }

    public function down(): void
    {
        foreach (['cache', 'cache_locks'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['expiration']);
                $table->integer('expiration')->change();
            });
        }
    }
};
