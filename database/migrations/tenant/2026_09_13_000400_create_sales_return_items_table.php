<?php

declare(strict_types=1);

use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a customer's return: which despatched line, how much of it, and what that credits.
 *
 * The mirror of `purchase_return_items`. **Each row names the sales order line it credits**,
 * which is what makes the ceiling a per-line question rather than a per-product one — the same
 * quantity of the same product sold twice at two prices is two ceilings, and crediting the wrong
 * one would credit the wrong money.
 *
 * **No `product_id` and no snapshot.** The product is `salesOrderItem.product`, read through a
 * relation that includes archived rows — one hop, and one fact stored once.
 *
 * `line_total` is {@see OrderTotals::line()} at the *returned* quantity, with the order's own
 * unit price and discount, written down for the reason every other line total in this schema is.
 *
 * **No soft deletes here either.** A return's lines are deleted and rewritten wholesale on every
 * edit, and nothing points at a `sales_return_items.id`. The ceiling excludes a *trashed return*
 * through the parent's global scope rather than through anything on this table — see
 * `SalesReturnItem::salesReturn()`, which must never gain `withTrashed()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(SalesReturn::class)->constrained()->cascadeOnDelete();
            // Not nullable: this reference is the line's identity, its price and its ceiling.
            $table->foreignIdFor(SalesOrderItem::class)->constrained()->restrictOnDelete();
            // The one number a person types on this screen.
            $table->decimal('quantity', 15, 4);
            // Copied from the order line, never typed — so a credit cannot refund a price the
            // customer was not charged.
            $table->decimal('unit_price', 15, 4);
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->boolean('taxable')->default(true);
            $table->decimal('line_total', 15, 4);
            $table->timestamps();

            // Named explicitly, like its opposite number — though for a weaker reason, which is
            // worth saying because the two files otherwise read identically. Laravel's
            // generated name here would be
            // `sales_return_items_sales_return_id_sales_order_item_id_unique`, 61 characters,
            // which does fit inside MySQL's 64-character limit where the purchase side's 70 did
            // not. Named anyway: three characters is not a margin worth relying on, and a pair
            // of tables that index the same way should say so the same way.
            $table->unique(
                ['sales_return_id', 'sales_order_item_id'],
                'sales_return_items_line_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
