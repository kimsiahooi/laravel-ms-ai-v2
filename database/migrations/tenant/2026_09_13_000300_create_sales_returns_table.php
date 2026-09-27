<?php

declare(strict_types=1);

use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods a customer sent back — a credit note with a stock leg, pointing the other way.
 *
 * The mirror of `purchase_returns`, column for column, and every argument that table makes
 * holds here: a document rather than a batch of movements, because what is being *credited* —
 * which despatch it came off, at what price it was sold, and why it came back — are commercial
 * facts that movements cannot carry; the rate and the currency copied from the order rather than
 * read from settings, because a return credits a charge already made; `number` allocated by
 * {@see DocumentNumberGenerator} under a row lock rather than typed.
 *
 * **It hangs off the order, and on this side that is a bigger change than on the other.** v1's
 * sales return named a customer and a quantity and nothing else — no order, no ceiling of any
 * kind — so it was an unbounded stock-in: anyone could add inventory on demand by declaring a
 * return, and no report would ever notice. Naming the order makes `fulfilled − already returned`
 * answerable, and {@see ReturnedQuantities} is the one place that answers it.
 *
 * **A sales order may only be returned against once it is fulfilled**, which is the mirror of
 * "received only" and load-bearing for the same reason across module boundaries:
 * `OpenSalesOrder::revise()` hard-deletes an order's lines on every edit and `sales_order_items`
 * has no soft deletes, so a return line pointing at a *pending* order's line would hit
 * `restrictOnDelete` and turn the sales-order edit screen into a 500 — a break in a module this
 * one never opens. "Fulfilled only" is also what makes those line ids stable enough to point at.
 *
 * {@see OrderTotals} works on this table's own columns, which is what makes its four totals
 * reproducible without reading another table.
 *
 * `restrictOnDelete` on the order for the reason its opposite number gives: a return without its
 * order is not diminished, it is meaningless. A soft delete never reaches a foreign key and the
 * order's own `destroy()` already refuses anything not pending, so this can only fire on a
 * force-delete by hand — exactly when the database should repeat what the controller says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();
            // Allocated by DocumentNumberGenerator with the `SR` prefix that has been sitting
            // in business settings since the settings screen was built.
            $table->string('number', 50)->unique();
            // The despatch being credited. Not nullable: a return that cannot name what it
            // credits has no price to copy and no ceiling to measure against.
            $table->foreignIdFor(SalesOrder::class)->constrained()->restrictOnDelete();
            // A string rather than an enum column, as `sales_orders.status` is: the set is
            // asserted by ReturnStatus in PHP, where adding one is a code change rather than
            // an ALTER against every tenant database.
            $table->string('status', 20)->default('pending');
            // Holds an App\Enums\ReturnReason code — the same enum the purchase side uses,
            // because "damaged" means the same thing whichever direction the goods travel.
            // Not nullable and with no default, for the reason that table gives.
            $table->string('reason', 20);
            // All three copied from the order at the moment the return is raised.
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_total', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()->constrained('users')->nullOnDelete();
            // The three completion columns ship with the table and stay null until the return
            // is completed — the shape `sales_orders` set with its own `fulfilled_*` trio.
            $table->foreignIdFor(User::class, 'completed_by')
                ->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            // Where the goods are being put *back*, which is the one place this table means
            // the opposite of its counterpart. Chosen at completion and defaulted to wherever
            // the despatch shipped from — but not pinned to it: returned goods routinely go to
            // a different shelf from the one they left, and sometimes to a different site.
            $table->foreignIdFor(Warehouse::class, 'completed_warehouse_id')
                ->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The one way this table is read: filtered by status, newest first. No second
            // index on `reason`, for the reason the purchase side gives.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
