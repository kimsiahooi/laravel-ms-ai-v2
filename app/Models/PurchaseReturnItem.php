<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One delivered line going back, and what that credits.
 *
 * No soft deletes and no traits, for the reason {@see PurchaseOrderItem} gives: a line only
 * exists inside its document, and `SavePurchaseReturn` replaces the lines wholesale rather than
 * diffing them.
 *
 * **The material is not a column here.** It is `purchaseOrderItem.rawMaterial`, one hop through
 * a relation that includes archived rows — so an archived material still names itself and a
 * hard-deleted one leaves the line still saying what was paid. Copying it would be a second
 * version of a fact that cannot change.
 *
 * @property int $id
 * @property int $purchase_return_id
 * @property int $purchase_order_item_id
 * @property string $quantity
 * @property string $unit_cost copied from the order line, never typed
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property bool $taxable
 * @property string $line_total OrderTotals::line() at the returned quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PurchaseReturn $purchaseReturn
 * @property-read PurchaseOrderItem $purchaseOrderItem
 */
class PurchaseReturnItem extends Model
{
    /**
     * The document this line belongs to.
     *
     * **Deliberately NOT `withTrashed()`, and that absence is load-bearing.** Two relations on
     * this module carry `withTrashed` and the house style makes it reflexive — but this is the
     * relation {@see ReturnedQuantities} builds its `whereHas` from, so the global scope riding
     * on it is what makes a deleted return release the quantity it was holding. Adding
     * `withTrashed()` here would have deleted returns count against the ceiling forever: a
     * refusal nobody could diagnose, on a screen that would insist a delivery had already been
     * returned.
     *
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * The delivered line being credited — the source of this line's money and its ceiling.
     *
     * No `withTrashed`: `purchase_order_items` has no soft deletes, and the table's
     * `restrictOnDelete` is what stops the row going anywhere.
     *
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:4',
            'taxable' => 'boolean',
            'line_total' => 'decimal:4',
        ];
    }
}
