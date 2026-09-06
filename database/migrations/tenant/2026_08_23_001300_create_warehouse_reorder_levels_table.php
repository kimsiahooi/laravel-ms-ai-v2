<?php

declare(strict_types=1);

use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The level at which one item in one warehouse wants restocking.
 *
 * **Policy, not ledger.** Everything else in Phase 4 records what happened;
 * this records what somebody decided ought to happen, which is why it lives
 * apart from `warehouse_stocks` rather than as another column on it. The two
 * have different lifetimes: a threshold can be set for an item that has never
 * been stocked here, and it survives the stock going to zero and back.
 *
 * **A row exists only when a level is set.** There is no `default(0)` and no
 * row for "no opinion" — setting a level to zero deletes the row instead,
 * because a threshold of zero and no threshold at all mean the same thing:
 * nothing to warn about. That keeps the table proportional to the decisions
 * actually taken rather than to catalogue × warehouses, and it means the
 * alert condition is `min_stock > 0` by construction rather than by rule.
 *
 * `cascadeOnDelete`, like `warehouse_stocks` and unlike the ledger: a
 * threshold for a warehouse nobody has any more is not a record of anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_reorder_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('min_stock', 15, 4);
            $table->timestamps();

            // Named explicitly: the generated name would be 72 characters, and MySQL
            // stops at 64. Same compound key as `warehouse_stocks`, for the same
            // reason — one row per (warehouse, item), so `updateOrCreate` cannot race
            // itself into two.
            $table->unique(
                ['warehouse_id', 'stockable_type', 'stockable_id'],
                'warehouse_reorder_levels_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_reorder_levels');
    }
};
