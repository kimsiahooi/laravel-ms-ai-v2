<?php

declare(strict_types=1);

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use App\Support\OrderTotals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of a purchase order: what was ordered, how much of it, and what was agreed
 * for it.
 *
 * **The price lives here, and it is the point of the row.** A quantity alone is a
 * movement, which the ledger already records. What only this line can say is what was
 * paid — the unit cost, whatever discount was negotiated on it, whether it is taxable,
 * and the figure the three come to. `line_total` is computed by
 * {@see OrderTotals::line()} and written down, for the reason the order's
 * own four totals are: a figure re-derived on every read is a figure that changes when
 * the arithmetic does, and an accounting record must not.
 *
 * **No unique index on (purchase_order_id, raw_material_id), deliberately.** A stock take
 * has one, because two lines counting the same shelf are two answers to one question.
 * An order is not that. The same material appearing twice at two prices is ordinary and
 * correct — tiered pricing is real, and so is "200 at the old quote, 300 at the new one".
 * Refusing the second line would be this table inventing a commercial rule the business
 * does not hold.
 *
 * **No snapshot column.** v1 carried a `raw_material_snapshot` JSON blob duplicating the
 * material's name, sku and unit at write time, so that an order still read correctly
 * after the catalogue changed. It went stale in the other direction instead: a typo
 * corrected in the catalogue stayed wrong on every order ever raised, the blob could not
 * be searched or joined, and a nullable FK sat beside it meaning something subtly
 * different. What an order must not lose is the money, and the money is in the columns
 * below. The identity comes from the material itself, read through a relation that
 * includes archived rows — see {@see PurchaseOrderItem}.
 *
 * `cascadeOnDelete` on the order, matching the document it belongs to: a line without its
 * order is not a record of anything. `nullOnDelete` on the material, for the reason the
 * order gives about its supplier — the catalogue is curated, the order is history, and
 * history does not get to be deleted because somebody tidied a list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(PurchaseOrder::class)->constrained()->cascadeOnDelete();
            // Raw materials only. A purchase order buys what the workspace consumes; the
            // things it makes leave by sales order, and offering both here would make
            // "buy your own finished goods back" a click away.
            $table->foreignIdFor(RawMaterial::class)->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            // Holds an App\Enums\DiscountType code — 'none', 'percent', 'amount'. Stored
            // rather than inferred, because 10 off a line and 10% off it come to the same
            // money today and to different money the moment the quantity changes.
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 15, 4)->default(0);
            // Most lines are; the exempt one is the exception somebody unticks. Per line
            // rather than per order, because an order routinely mixes the two.
            $table->boolean('taxable')->default(true);
            // Quantity × unit cost, less the discount, at the working scale. Not rounded
            // to the currency here: rounding happens once, on the order's own figures,
            // or a hundred lines each shaved by half a cent move the total.
            $table->decimal('line_total', 15, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
