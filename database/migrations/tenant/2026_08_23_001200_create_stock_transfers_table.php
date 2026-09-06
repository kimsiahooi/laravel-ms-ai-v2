<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One movement of stock between two warehouses, as a document.
 *
 * **Why this exists when the ledger already records the effect.** A transfer writes two
 * rows to `stock_movements` — one negative at the source, one positive at the
 * destination — and each of those knows only its own warehouse. Nothing in the ledger
 * says the two belong together, so "what was transferred, and where to" is a question
 * the ledger cannot answer. This row is that answer.
 *
 * It is not a duplicate of the two movements. They say what happened to each warehouse;
 * this says what somebody did. The numbers coincide, the statements do not.
 *
 * `quantity` is a positive magnitude here, unlike the ledger's signed column: a document
 * has no direction to encode, because `from` and `to` are already columns.
 *
 * Append-only, like the ledger it drives: no soft delete, no status, nothing to edit. A
 * transfer sent the wrong way is corrected by transferring back, which leaves both the
 * mistake and the correction on the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            // Both restricted rather than cascaded, for the reason the ledger gives:
            // removing a warehouse must never quietly erase what moved through it.
            $table->foreignIdFor(Warehouse::class, 'from_warehouse_id')
                ->constrained('warehouses')->restrictOnDelete();
            $table->foreignIdFor(Warehouse::class, 'to_warehouse_id')
                ->constrained('warehouses')->restrictOnDelete();
            // A product or a raw material — see the morph map in AppServiceProvider.
            $table->morphs('stockable');
            $table->decimal('quantity', 15, 4);
            // Nulled rather than cascaded: losing a person must not erase what they moved.
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            // "What has moved out of here" and "what has moved into here" are both real
            // questions, and both are read newest-first.
            $table->index(['from_warehouse_id', 'created_at']);
            $table->index(['to_warehouse_id', 'created_at']);
            $table->index(['stockable_type', 'stockable_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
