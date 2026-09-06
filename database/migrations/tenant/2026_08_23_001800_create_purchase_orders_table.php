<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods ordered from a supplier — the commitment, not the stock.
 *
 * **Why a document rather than a batch of receipts.** Receiving an order writes ordinary
 * `stock_movements` rows, and those say what arrived. They cannot say what was *agreed*:
 * the price per unit, the discount somebody negotiated, the tax that applies, the date
 * the goods were promised for. None of that is a movement, and an order that is only its
 * receipts loses every figure a person would want to reconcile against an invoice.
 *
 * **`number` is unique here, and that is the fix.** v1 made this column nullable, filled
 * it from a text box on the form, and enforced uniqueness in a FormRequest alone — so a
 * number somebody typed could collide with one another request had just typed, and the
 * only thing standing in the way was a check that read the table before writing it.
 * v1 also shipped a working number generator that no purchase order ever called.
 * {@see DocumentNumberGenerator} allocates it under a row lock now, and this
 * index is what makes that promise enforceable rather than merely intended.
 *
 * **Four totals, stored.** v1 computed an order's total as a PHP float inside its Data
 * class, on every read, and persisted nothing — so the figure was re-derived rather than
 * recorded, could not be summed by the database, could not be reconciled against an
 * invoice, and drifted the day the arithmetic changed. {@see OrderTotals}
 * decides these four and they are written down. v1 had no discount and no tax on a
 * purchase order at all.
 *
 * **`tax_rate` is snapshotted, and that is the whole reason it is a column here rather
 * than a lookup.** The rate lives in `business_settings`, where somebody may change it
 * tomorrow; an order must keep the rate it was raised under, or last year's orders would
 * silently restate themselves the moment the business registers for a different band.
 * `currency` and `exchange_rate` are the same argument applied to money: the order is
 * denominated in what was agreed, and carries its own rate back to the base currency.
 *
 * **Two people, not one.** v1 had a single `user_id` that meant "whoever created it" and
 * never recorded who received the goods — the one irreversible step in this document's
 * life had no name attached. Both columns are nullable and nulled on delete, like every
 * other actor column here: losing a person must not erase what was ordered.
 *
 * `nullOnDelete` on the supplier, not cascade: a supplier is deleted (softly, and only
 * hard by a hand at the database) long after the orders placed with them, and those
 * orders are accounting records. An order that can no longer name who it was placed with
 * is diminished; an order that vanishes is a hole in the books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            // Allocated by DocumentNumberGenerator, never typed. Unique because a
            // document number that is not unique is not a document number — see above.
            $table->string('number', 50)->unique();
            $table->foreignIdFor(Supplier::class)->nullable()->constrained()->nullOnDelete();
            // A string rather than an enum column, as `stock_movements.reason` is: the
            // set of statuses is asserted by PurchaseOrderStatus in PHP, where adding
            // one is a code change rather than an ALTER against every tenant database.
            $table->string('status', 20)->default('pending');
            // ISO 4217, three letters, exactly as `business_settings.base_currency` is.
            $table->char('currency', 3);
            // Base-currency units per one unit of the order currency; 1 when the order
            // is already in the base currency. Six places because FX rates are quoted at
            // more precision than money is held at.
            $table->decimal('exchange_rate', 15, 6)->default(1);
            // The percentage this order was raised under — 6% is stored as 6.0000, the
            // same shape `business_settings.tax_rate` uses. Snapshotted, never read back
            // from settings: see the class note.
            $table->decimal('tax_rate', 8, 4)->default(0);
            // What OrderTotals worked out, written down rather than re-derived. All four
            // default to zero so an order with no lines is a real row rather than a null
            // that every reader has to defend against.
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->text('notes')->nullable();
            // A timestamp, stored in UTC like every other instant in this schema, and
            // chosen deliberately over a bare `date`.
            //
            // The screen still asks for a day — nobody promises a delivery at 14:30 — and
            // the day somebody picks is anchored to the start of that day **in the zone
            // they picked it from**, then converted. So the person who set it always reads
            // back the date they chose.
            //
            // The trade is real and worth naming: an instant renders on the reader's
            // clock, so a colleague far enough west can see the day before. A `date`
            // column would avoid that by having no zone at all, at the cost of not being
            // comparable with `received_at` and the rest of the ledger, which are
            // instants. One workspace, one working zone, is the case this is built for.
            $table->timestamp('expected_date')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'received_by')
                ->nullable()->constrained('users')->nullOnDelete();
            // Null until received, and never cleared afterwards — this and `received_by`
            // are the whole audit trail of the one step that moves stock.
            $table->timestamp('received_at')->nullable();
            // Where the goods landed. Chosen at receipt rather than when the order is
            // raised: which door a lorry backs up to is not known weeks in advance, and
            // v1 asking for it up front meant the field was routinely wrong.
            $table->foreignIdFor(Warehouse::class, 'received_warehouse_id')
                ->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The one way this table is read: filtered by status, newest first. Leading
            // with `status` is what lets "what is still outstanding" answer from the
            // index rather than from a scan.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
