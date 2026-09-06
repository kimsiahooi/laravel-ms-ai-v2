<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which columns each user looks at, per list.
 *
 * Nullable, and empty for almost everybody: only a list somebody has actually changed is
 * stored, and resetting one removes its entry again. So "never touched" and "back to
 * default" are the same state, and the column stays small.
 *
 * Its own column rather than a general `preferences` bag, following `locale` directly
 * above it — this app's precedent for a per-user preference is one column per preference,
 * and a bag would be guessing at a shape nothing needs yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('table_columns')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('table_columns');
        });
    }
};
