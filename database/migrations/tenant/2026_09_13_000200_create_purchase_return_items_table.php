<?php

declare(strict_types=1);

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a purchase return: which delivered line is going back, how much of it, and what
 * that credits.
 *
 * **The row points at an order line, not at a material.** That is what makes both halves of
 * this module work. There is deliberately no unique index on `(purchase_order_id,
 * raw_material_id)` — the same material at two prices on one order is ordinary — so "copy the
 * money from the parent line" has no answer at all unless the return names *which* line. And
 * the ceiling is then per-line and exact rather than a sum on both sides that cannot say which
 * price to credit.
 *
 * **Order line ids are stable by the time a return can exist.** `OpenPurchaseOrder::revise()`
 * hard-deletes an order's lines and re-inserts them on every edit, which would make an id a
 * poor thing to point at — except that `revise()` is only reachable while the order is pending,
 * and a return can only be raised against one that has been received. The window in which the
 * ids churn is exactly the window in which no return can exist. `restrictOnDelete` is what
 * enforces that rather than trusting it: were a return ever raised against a pending order, the
 * next edit of that order would be refused by the database instead of quietly orphaning a
 * credit note.
 *
 * **Unique on (return, order line), unlike an order's own lines.** A return line *is* a
 * reference to one delivered line, and its price comes from that line — so two rows naming the
 * same one are two answers to a question that has one. The stock take's argument, not the
 * order's. {@see PurchaseReturnItem} and the FormRequest's `distinct` rule say the same thing
 * one layer up, because a crafted payload reaching this index produces a 500 rather than a
 * sentence.
 *
 * **No `raw_material_id` and no snapshot.** The material is `purchaseOrderItem.rawMaterial`,
 * read through a relation that includes archived rows — one hop, and one fact stored once.
 * Copying it here would be a second version of something that cannot change anyway.
 *
 * `line_total` is {@see OrderTotals::line()} at the *returned* quantity, with the order's own
 * unit cost and discount. It is written down for the reason every other line total in this
 * schema is: a figure re-derived on every read changes when the arithmetic does, and an
 * accounting record must not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(PurchaseReturn::class)->constrained()->cascadeOnDelete();
            // Not nullable: this reference is the line's identity, its price and its ceiling.
            $table->foreignIdFor(PurchaseOrderItem::class)->constrained()->restrictOnDelete();
            // The one number a person types on this screen.
            $table->decimal('quantity', 15, 4);
            // Copied from the order line, never typed — so a credit cannot claim a price the
            // delivery was not charged at.
            $table->decimal('unit_cost', 15, 4);
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->boolean('taxable')->default(true);
            $table->decimal('line_total', 15, 4);
            $table->timestamps();

            // Named explicitly, and it has to be: Laravel's generated name for this pair is
            // `purchase_return_items_purchase_return_id_purchase_order_item_id_unique`, which
            // is 70 characters against MySQL's 64-character limit for an identifier. The
            // failure is an `errno 1059` on a `create table` that has already half succeeded,
            // so the table lands without its index and the migration row is never written.
            $table->unique(
                ['purchase_return_id', 'purchase_order_item_id'],
                'purchase_return_items_line_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
