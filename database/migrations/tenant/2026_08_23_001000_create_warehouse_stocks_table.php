<?php

declare(strict_types=1);

use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-hand per (warehouse, item) — the running total of the `stock_movements` ledger.
 *
 * Derived data, kept because the alternative is a `SUM` over every movement ever
 * recorded each time a screen asks what is in stock. StockService is the only writer,
 * and it writes here and to the ledger inside one transaction, which is what stops the
 * two from disagreeing.
 *
 * **The compound unique key is load-bearing, not tidiness.** It is what makes "the
 * on-hand row for this item in this warehouse" a single row that can be locked, and
 * under MySQL's REPEATABLE READ it is also what gives `SELECT … FOR UPDATE` a gap to
 * lock when the row does not exist yet — so two concurrent first-movements for the same
 * item serialise instead of both inserting.
 *
 * `cascadeOnDelete`, unlike the ledger's restrict: a running total for a warehouse that
 * no longer exists is not a record of anything, and the ledger still holds the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->cascadeOnDelete();
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'stockable_type', 'stockable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
