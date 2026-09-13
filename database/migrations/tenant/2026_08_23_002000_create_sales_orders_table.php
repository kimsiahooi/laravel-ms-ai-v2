<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods sold to a customer — the commitment, not the stock.
 *
 * The mirror of `purchase_orders`, and deliberately shaped the same way: one document
 * carrying what was agreed, and a single irreversible step that moves the goods. Where a
 * purchase order *receives* into a warehouse, this one *issues* out of one, and that is
 * the only structural difference between them.
 *
 * **Why a document rather than a batch of movements.** Fulfilling an order writes ordinary
 * `stock_movements` rows, and those say what left. They cannot say what was *sold*: the
 * price per unit, the discount that was given, the tax that applies, the date it was
 * promised for. An order that is only its movements loses every figure somebody would
 * reconcile against an invoice.
 *
 * **`number` is unique, allocated, never typed.** {@see DocumentNumberGenerator} takes it
 * under a row lock against `business_settings.sales_order_prefix`. v1 made the column
 * nullable, filled it from a text box, and defended uniqueness with a read-before-write in
 * a FormRequest — which is not a defence, only a narrower window. It then needed a retry
 * loop *because* it had no index. This one has the index, so the retry loop is unnecessary.
 *
 * **Four totals, stored.** v1 computed a sales order's total as a PHP float inside its Data
 * class on every read and persisted nothing, so the figure could not be summed by the
 * database, could not be reconciled, and would drift the day the arithmetic changed.
 * {@see OrderTotals} decides them and they are written down. v1 also had no discount on a
 * sales order at all.
 *
 * **`tax_rate`, `currency` and `exchange_rate` are snapshotted**, for the reason the
 * purchase-order migration gives at length: settings change, and an order must keep the
 * terms it was raised under or last year's books silently restate themselves. Note
 * `currency` carries no default — v1 defaulted to `'USD'`, which is simply the wrong
 * answer in a workspace whose base is MYR, and the request always sends one anyway.
 *
 * **Two people.** v1 had a single `user_id` meaning "whoever created it" and never recorded
 * who shipped the goods — the one irreversible step in this document's life had no name on
 * it. Both are nullable and nulled on delete: losing a person must not erase what was sold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignIdFor(Customer::class)->nullable()->constrained()->nullOnDelete();
            // A string rather than an enum column, matching `purchase_orders.status`:
            // the set is asserted by SalesOrderStatus in PHP, where adding one is a code
            // change rather than an ALTER against every tenant database.
            $table->string('status', 20)->default('pending');
            // ISO 4217. No default — see the class note.
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->text('notes')->nullable();
            // The delivery date that was promised — a date the business wrote down, not a
            // moment on a clock, and the one column here that is not an instant.
            //
            // **Nothing converts it, in either direction.** The screen asks for a day and
            // optionally a time on it; exactly that is stored and exactly that is shown, so
            // "the 15th" still means the 15th after somebody changes the workspace timezone.
            // That setting is a display reference for timestamps and never decides what goes
            // into a column. A day with no time is stored at midnight, and that is how the
            // absence is recorded — unambiguous precisely because there is no zone to shift
            // it. A `timestamp` rather than a `date` so it can hold the optional time.
            $table->timestamp('expected_date')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'fulfilled_by')
                ->nullable()->constrained('users')->nullOnDelete();
            // Null until fulfilled, never cleared afterwards — this and `fulfilled_by` are
            // the whole audit trail of the one step that moves stock.
            $table->timestamp('fulfilled_at')->nullable();
            // Which warehouse the goods left. Chosen at fulfilment rather than when the
            // order is raised, mirroring the receipt side: which shelf a thing ships from
            // is not known when the order is taken, and asking up front means a wrong
            // answer stored for weeks.
            $table->foreignIdFor(Warehouse::class, 'fulfilled_warehouse_id')
                ->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The one way this table is read: filtered by status, newest first. Leading
            // with `status` lets "what is still to ship" answer from the index.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
