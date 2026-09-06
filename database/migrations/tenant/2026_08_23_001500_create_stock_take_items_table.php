<?php

declare(strict_types=1);

use App\Models\StockTake;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a count sheet: an item, what the system believed, what somebody counted,
 * and what that turned out to move.
 *
 * **Three quantities, because they answer three different questions.** v1 stored two and
 * conflated the last one with the first, which is the bug this table is shaped to avoid.
 *
 * `system_quantity` is the snapshot taken when the line joined the sheet. It is display
 * only — it is what the counter is asked to confirm, and it goes stale the moment
 * anything else moves that item. Nothing in the posting arithmetic reads it.
 *
 * `counted_quantity` is nullable, and that nullability is the point: NULL means "not
 * counted yet", which is a different statement from "counted, and there are zero". v1
 * preseeded every line to the system quantity, so an untouched line was indistinguishable
 * from one deliberately confirmed at the expected number — and posting an empty sheet
 * therefore wrote the snapshot back over live on-hand.
 *
 * `applied_delta` is what posting actually applied, computed under the row lock at
 * posting time. It is deliberately not a stored variance: v1 stored
 * `counted - system_quantity` and then posted `counted - live on-hand`, so whenever
 * stock shifted between the snapshot and the post, the number printed on the line was
 * not the number that moved. This column is written by the posting run and nowhere else,
 * which makes it the truth rather than a guess about the truth.
 *
 * `cascadeOnDelete`, matching the sheet it belongs to: a line without its count is not a
 * record of anything, and the ledger holds whatever the posting produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(StockTake::class)->constrained()->cascadeOnDelete();
            // A product or a raw material — see the morph map in AppServiceProvider.
            $table->morphs('stockable');
            $table->decimal('system_quantity', 15, 4);
            $table->decimal('counted_quantity', 15, 4)->nullable();
            $table->decimal('applied_delta', 15, 4)->nullable();
            $table->timestamps();

            // Named explicitly: the generated name would be 65 characters and MySQL stops
            // at 64. One line per (take, item) — the sheet is open, so an item found on
            // the shelf can be added to it, and adding one that is already listed has to
            // collide rather than quietly produce two lines that disagree about the count.
            $table->unique(
                ['stock_take_id', 'stockable_type', 'stockable_id'],
                'stock_take_items_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
