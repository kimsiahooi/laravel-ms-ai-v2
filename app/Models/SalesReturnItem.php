<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiscountType;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One despatched line coming back, and what that credits.
 *
 * No soft deletes and no traits, for the reason {@see SalesOrderItem} gives: a line only exists
 * inside its document, and `SaveSalesReturn` replaces the lines wholesale rather than diffing
 * them.
 *
 * **The product is not a column here.** It is `salesOrderItem.product`, one hop through a
 * relation that includes archived rows — so an archived product still names itself and a
 * hard-deleted one leaves the line still saying what was charged.
 *
 * @property int $id
 * @property int $sales_return_id
 * @property int $sales_order_item_id
 * @property string $quantity
 * @property string $unit_price copied from the order line, never typed
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property bool $taxable
 * @property string $line_total OrderTotals::line() at the returned quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SalesReturn $salesReturn
 * @property-read SalesOrderItem $salesOrderItem
 */
class SalesReturnItem extends Model
{
    /**
     * The document this line belongs to.
     *
     * **Deliberately NOT `withTrashed()`, and that absence is load-bearing** — the same trap
     * {@see PurchaseReturnItem::purchaseReturn()} guards on the other side. This is the
     * relation {@see ReturnedQuantities} builds its `whereHas` from, so the global scope riding
     * on it is what makes a deleted return release the quantity it was holding. Adding
     * `withTrashed()` here would have deleted returns count against the ceiling forever: a
     * refusal nobody could diagnose, on a screen insisting a despatch had already been credited.
     *
     * @return BelongsTo<SalesReturn, $this>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * The despatched line being credited — the source of this line's money and its ceiling.
     *
     * No `withTrashed`: `sales_order_items` has no soft deletes, and the table's
     * `restrictOnDelete` is what stops the row going anywhere.
     *
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:4',
            'taxable' => 'boolean',
            'line_total' => 'decimal:4',
        ];
    }
}
