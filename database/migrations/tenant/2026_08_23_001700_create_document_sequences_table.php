<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The counter behind a document number.
 *
 * A purchase order is `PO-2026-0001` because this table holds the 1. The alternative —
 * `MAX(number) + 1` at insert time — reads a column two transactions can read at once and
 * hands both of them the same answer; this row can be locked, so the second waits.
 *
 * **One row per (type, period), and the unique index is what makes it safe.** The
 * generator's first act is an `insertOrIgnore`, which is only race-free because a second
 * insert of the same pair collides with this index rather than creating a rival counter.
 *
 * `period` is the financial year label, or an empty string when numbering never resets.
 * Keeping it in the key rather than in the number's format means "restart each year" is a
 * new row instead of a migration.
 *
 * The number itself still lands on the document, as a stored column with its own unique
 * index. This table is the allocator; it is not the record of what was allocated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            // 'purchase_order', 'sales_order', … — the document type, not its prefix.
            // The prefix is a setting somebody may change; the sequence must not restart
            // when they do.
            $table->string('type', 50);
            // '2026', or '' when number_reset is 'never'. Not nullable: a null would make
            // the unique index below stop enforcing anything, since MySQL treats each
            // null as distinct and two counters could then exist for the same type.
            $table->string('period', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
