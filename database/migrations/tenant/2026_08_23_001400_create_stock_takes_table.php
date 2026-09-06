<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical count of one warehouse — the sheet, not the effect.
 *
 * **Why a document rather than a batch of movements.** Posting a count writes ordinary
 * `stock_movements` rows, and those say what changed. They cannot say what was
 * *looked at*: a line counted at exactly the expected number moves nothing and so
 * leaves no ledger trace at all, yet it is the most important thing a count records —
 * somebody walked to that shelf and confirmed it. This table and its lines are where
 * that confirmation lives.
 *
 * **A draft is a working document, and that is why this one is mutable.** Everything
 * else in Phase 4 is append-only; a count is entered over minutes or hours, corrected
 * as the counter goes, and is worth nothing until it is posted. So it carries a status,
 * it is edited in place, and it soft-deletes — an abandoned draft is somebody changing
 * their mind, not a record to keep forever. Once `posted`, though, it stops moving: a
 * posted count is corrected by counting again, which leaves both counts on the record
 * the same way an opposite movement corrects the ledger.
 *
 * **Two people, not one.** v1 had a single `user_id` and overwrote it with the poster at
 * posting time, so whoever opened the sheet became unknowable the moment it was posted —
 * exactly when knowing both matters. Both are nullable and nulled on delete, like every
 * other actor column here: losing a person must not erase what was counted.
 *
 * `cascadeOnDelete` on the warehouse, unlike the ledger's restrict: a count of a
 * warehouse nobody has any more is not a record of anything, and the movements it posted
 * survive on their own — they are attached to the warehouse under restrict, and their
 * notes still name the count that caused them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Warehouse::class)->constrained()->cascadeOnDelete();
            // A string rather than an enum column, as `stock_movements.reason` is: the
            // set of statuses is asserted by StockTakeStatus in PHP, where adding one is
            // a code change rather than a migration against every tenant database.
            $table->string('status', 20)->default('draft');
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'posted_by')
                ->nullable()->constrained('users')->nullOnDelete();
            // Null until posted, and never cleared afterwards — the pair of this and
            // `posted_by` is the whole audit trail of the one irreversible step.
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The one way this table is read: filtered by status, newest first. Leading
            // with `status` is what lets "the drafts still open" answer from the index
            // rather than from a scan.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
