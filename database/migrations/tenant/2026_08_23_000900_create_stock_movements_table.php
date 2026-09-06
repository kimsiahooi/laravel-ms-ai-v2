<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock ledger — append-only, and the truth about what happened.
 *
 * No `updated_at` is meaningful here and no soft delete exists, because a row is never
 * changed or removed: a mistake is corrected by writing the opposite movement, which is
 * how a stock record stays auditable. `warehouse_stocks` is the running total derived
 * from this table, kept current only so a read is one row rather than a SUM.
 *
 * **`quantity` is signed. There is no direction column** — positive is in, negative is
 * out. One column rather than two means a total is `SUM(quantity)` and never a CASE, and
 * it makes "in" and "out" impossible to disagree with each other.
 *
 * `restrictOnDelete` on the warehouse, not cascade: deleting a warehouse must never
 * silently erase the history of what moved through it. Nothing offers a hard delete
 * today, and this is what keeps that true if something ever does.
 *
 * **`source` says which document caused the row, as a relationship rather than prose.**
 * v1 had no such column and so spelled the reference into `notes` in six separate
 * Actions — `"PO #{$order->id}"`, `"SO #…"`, `"MO #…"`, and three more. That makes the
 * reference a sentence, which can only be one language; makes reading it back a parse;
 * and leaves `notes` meaning two things at once, so neither can be trusted alone. Phase 5
 * brings those six callers here, and every one of them passes `$source` instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->restrictOnDelete();
            // A product or a raw material — see the morph map in AppServiceProvider,
            // which stores 'product' / 'raw_material' rather than a class name.
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4);
            $table->string('reason', 30);
            // Nulled rather than cascaded: losing a person must not erase the movements
            // they recorded. A movement with no name is still a movement.
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            // What a person typed, and only that. The document that caused the row is
            // the `source` pair below, not a sentence spelled into here.
            $table->text('notes')->nullable();
            // What caused this row: a stock take, a transfer, later an order.
            //
            // Nullable because most rows have no document — a hand-recorded adjustment
            // is caused by a person and nothing else, which is an absent value rather
            // than a missing one. Written through the morph map, so the column holds
            // 'stock_take' rather than a class name a namespace change would strand.
            //
            // No foreign key, and not only because a morph cannot have one: this table
            // outlives what it points at, the same reason the warehouse above is
            // restricted rather than cascaded. A stock take can be deleted and the rows
            // it posted must stay, still able to say where they came from.
            $table->nullableMorphs('source');
            $table->timestamps();

            // The two questions this table is asked: "what happened in this warehouse"
            // and "what happened to this item". Both are date-ordered, so the timestamp
            // is part of the index rather than a sort afterwards.
            $table->index(['warehouse_id', 'created_at']);
            $table->index(['stockable_type', 'stockable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
