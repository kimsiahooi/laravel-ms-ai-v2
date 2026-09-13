<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a sales order: what was sold, how much of it, and what was agreed for it.
 *
 * **The price lives here, and it is the point of the row.** A quantity alone is a movement,
 * which the ledger already records. What only this line can say is what was charged — the
 * unit price, whatever discount was given, whether it is taxable, and the figure the three
 * come to. `line_total` is computed by {@see OrderTotals::line()} and written down, because
 * a figure re-derived on every read changes when the arithmetic does, and an accounting
 * record must not.
 *
 * **`unit_price`, and the name is not incidental.** {@see OrderTotals::forOrder()} already
 * reads `unit_price`; the purchase side has to rename `unit_cost` into it before doing the
 * arithmetic. Selling is the direction the shared code was written in, so this table needs
 * no translation layer at all.
 *
 * **No unique index on (sales_order_id, product_id), deliberately** — the same call the
 * purchase-order items table makes and for the same reason. The same product twice at two
 * prices is ordinary: "50 at the quoted price, 20 at the discount". Refusing the second line
 * would be this table inventing a commercial rule the business does not hold.
 *
 * That has one consequence worth stating here, because it is easy to get wrong where it
 * matters: **fulfilment must aggregate per product before checking stock.** Two lines of 5
 * against 8 on hand must be refused, and a per-line check would pass both. See
 * `FulfillSalesOrder`.
 *
 * **No snapshot column.** v1 carried a `product_snapshot` JSON blob duplicating the name,
 * sku and unit at write time so an order still read correctly after the catalogue changed.
 * It went stale in the other direction instead: a typo corrected in the catalogue stayed
 * wrong on every order ever raised, the blob could not be searched or joined, and a nullable
 * FK sat beside it meaning something subtly different. What an order must not lose is the
 * money, and the money is in the columns below. Identity comes from the product itself, read
 * through a relation that includes archived rows — see {@see SalesOrderItem}.
 *
 * `cascadeOnDelete` on the order: a line without its order records nothing. `nullOnDelete`
 * on the product, because the catalogue is curated and the order is history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SalesOrder::class)->constrained()->cascadeOnDelete();
            // Products only. A sales order sells what the workspace makes; what it consumes
            // arrives by purchase order, and offering both here would put "sell your own
            // inputs" one click away.
            $table->foreignIdFor(Product::class)->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_price', 15, 4);
            // Holds an App\Enums\DiscountType code — 'none', 'percent', 'amount'. Stored
            // rather than inferred, because 10 off a line and 10% off it come to the same
            // money today and to different money the moment the quantity changes.
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 15, 4)->default(0);
            // Most lines are; the exempt one is the exception somebody unticks. Per line
            // rather than per order, because an order routinely mixes the two.
            $table->boolean('taxable')->default(true);
            // Quantity × unit price, less the discount, at the working scale. Not rounded to
            // the currency here: rounding happens once, on the order's own figures, or a
            // hundred lines each shaved by half a cent move the total.
            $table->decimal('line_total', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
    }
};
