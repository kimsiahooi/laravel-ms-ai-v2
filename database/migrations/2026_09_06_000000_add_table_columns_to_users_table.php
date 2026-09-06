<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The central half of the per-user column layout — see the tenant migration of the same
 * name for what it holds.
 *
 * Two of the ten lists that carry the Columns panel are the /admin tenant screens, and
 * they authenticate a CentralUser against *this* table. Without this column those two
 * would be the only lists that forget, which reads as a bug rather than a boundary.
 *
 * Note this is where the parallel with `locale` ends: that one is tenant-only, because a
 * super-admin has no language preference to store. A column layout they do have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('table_columns')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('table_columns');
        });
    }
};
