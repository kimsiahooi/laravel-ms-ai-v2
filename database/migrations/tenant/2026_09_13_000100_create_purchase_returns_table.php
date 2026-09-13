<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods going back to a supplier — a credit note with a stock leg.
 *
 * **Why a document rather than a batch of outward movements.** Completing a return writes
 * ordinary `stock_movements` rows, and those say what left the building. They cannot say what
 * is being *credited*: which delivery it came off, at what price it was bought, and why it is
 * going back. Those are commercial facts, they are agreed once, and they belong on a row of
 * their own.
 *
 * **It hangs off the order, and that is the fix.** v1's return named a supplier and nothing
 * else, and its only check was that the material had appeared on *some* received order from
 * that supplier — any order, any date, any quantity. So there was no ceiling at all: you could
 * return ten thousand of something you bought five of, and do it again tomorrow. Naming the
 * order makes `ordered − already returned` answerable, and {@see ReturnedQuantities} is the one
 * place that answers it.
 *
 * **The rate and the currency are copied from the order, never read from settings.** This is
 * the one place this table departs from `purchase_orders`, which re-snapshots the *current* tax
 * rate when an order is edited — an order being edited is an order being raised again. A return
 * is not. It credits a charge that has already been made, and computing it at today's rate
 * would credit a different figure from the one that was charged, so the two documents would
 * never reconcile. {@see OrderTotals} then works on the return's own columns, which is what
 * makes its four totals reproducible without reading another table.
 *
 * **`number` is unique, and allocated rather than typed** — {@see DocumentNumberGenerator}
 * under a row lock, with the `PR` prefix that has been sitting in business settings since the
 * settings screen was built. The index is what makes that promise enforceable rather than
 * merely intended.
 *
 * `restrictOnDelete` on the order, where `purchase_orders` uses `nullOnDelete` for its
 * supplier. The difference is that a return without its order is not diminished, it is
 * meaningless: its money was copied from that order's lines and its ceiling is measured against
 * them. A soft delete never reaches a foreign key, and the order's own `destroy()` already
 * refuses anything not pending — so this can only fire on a force-delete by hand, which is
 * exactly the moment the database should repeat what the controller says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            // Allocated by DocumentNumberGenerator, never typed — see the class note.
            $table->string('number', 50)->unique();
            // The delivery being credited. Not nullable: a return that cannot name what it
            // credits has no price to copy and no ceiling to measure against.
            $table->foreignIdFor(PurchaseOrder::class)->constrained()->restrictOnDelete();
            // A string rather than an enum column, as `purchase_orders.status` is: the set is
            // asserted by ReturnStatus in PHP, where adding one is a code change rather than
            // an ALTER against every tenant database.
            $table->string('status', 20)->default('pending');
            // Holds an App\Enums\ReturnReason code. Not nullable and with no default: unlike
            // a status there is no value that can be assumed on somebody's behalf, and a
            // return that does not say why is the one nobody can act on later.
            $table->string('reason', 20);
            // All three copied from the order at the moment the return is raised. See the
            // class note on why this is not a lookup.
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tax_rate', 8, 4)->default(0);
            // What OrderTotals worked out over the returned quantities, written down rather
            // than re-derived, for the reason `purchase_orders` gives about its own four.
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()->constrained('users')->nullOnDelete();
            // The three completion columns ship with the table and stay null until the return
            // is completed — the shape `purchase_orders` set with `received_by`,
            // `received_at` and `received_warehouse_id`. Declaring them now rather than
            // altering every tenant database later is the cheaper of the two, and the set of
            // them being null is what says this has not happened yet.
            $table->foreignIdFor(User::class, 'completed_by')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            // Where the goods actually left from. Chosen at completion rather than when the
            // return is raised, and defaulted to wherever the delivery landed — but not
            // pinned to it, because stock transfers are real and goods move before somebody
            // decides to send them back.
            $table->foreignIdFor(Warehouse::class, 'completed_warehouse_id')
                ->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The one way this table is read: filtered by status, newest first. There is no
            // second index on `reason` — five values over a few hundred rows is a filter this
            // index already narrows for, and an index nothing chooses is a write cost with no
            // reader.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
