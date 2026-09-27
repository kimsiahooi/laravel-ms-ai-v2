<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Goods a customer sent back, credited against the despatch they went out on.
 *
 * The mirror of {@see PurchaseReturn}, and the one asymmetry worth holding in mind is the
 * direction: completing this one puts stock *back* on a shelf, so it can never be refused for
 * want of it. What it can still be refused for is the ceiling — the delivery may have no room
 * left — and {@see ReturnedQuantities} is the one place that question is answered.
 *
 * **No `#[Fillable]`, and `$guarded` left at its default.** `SaveSalesReturn`,
 * {@see CompleteSalesReturn} and {@see DeleteSalesReturn} are the only things that write one,
 * and each names every column it sets. Nothing here is ever mass-assigned from a request.
 *
 * **The four totals are stored, not derived.** `OrderTotals` computed them once over the
 * returned quantities at the order's own prices, and re-deriving them on read would let the
 * arithmetic change a figure somebody has already been credited.
 *
 * @property int $id
 * @property string $number
 * @property int $sales_order_id
 * @property ReturnStatus $status
 * @property ReturnReason $reason
 * @property string $currency
 * @property string $exchange_rate
 * @property string $tax_rate the percentage the ORDER was raised under, copied
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $completed_warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read SalesOrder $salesOrder
 * @property-read Collection<int, SalesReturnItem> $items
 * @property-read User|null $creator
 * @property-read User|null $completer
 * @property-read Warehouse|null $completedWarehouse
 */
class SalesReturn extends Model
{
    use SoftDeletes;

    /**
     * What searching returns means.
     *
     * The return's own number first, because that is what somebody is holding. Then the order's
     * number, which is the other piece of paper on the desk. Then the customer's name, and the
     * notes.
     *
     * **Status and reason are deliberately not searched**, and **the lines are not searched
     * either** — both for the reasons {@see PurchaseReturn::search()} sets out.
     *
     * @param  Builder<SalesReturn>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('number', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('salesOrder', fn (Builder $order) => $order->where('number', 'like', $like))
                ->orWhereHas('salesOrder.customer', fn (Builder $customer) => $customer->where('name', 'like', $like));
        });
    }

    /**
     * The despatch being credited.
     *
     * `withTrashed()` because a document whose whole identity is "what this credits" must still
     * be able to say so. In practice it cannot happen — a fulfilled order is refused by
     * `SalesOrderController::destroy()` and so is never soft-deleted — which is exactly why the
     * relation should not be the thing that breaks if that ever changes.
     *
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class)->withTrashed();
    }

    /** @return HasMany<SalesReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who took the goods back in, which is routinely not who raised the credit note.
     *
     * Null on anything still pending, and null again once that person has been force-deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Where the goods went back onto the shelf.
     *
     * Chosen at completion and defaulted to wherever the despatch shipped from, without being
     * pinned to it: returned goods routinely go to a different shelf from the one they left.
     * Null while pending.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function completedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'completed_warehouse_id');
    }

    /**
     * Money and rates read back as strings, never floats — the rule {@see SalesOrder} states in
     * full. `decimal:6` on the rate alone, because its column is.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'exchange_rate' => 'decimal:6',
            'tax_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }
}
