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
 * Goods going back to the supplier who delivered them.
 *
 * **A credit against one delivery.** Everything commercial about it — the currency, the rate,
 * the tax rate, the price on every line — is copied from the purchase order it names, because a
 * return credits a charge that has already been made. The migration argues that at length; what
 * matters here is that none of it is ever read back from settings.
 *
 * **No `#[Fillable]`, and `$guarded` left at its default**, for the reason {@see PurchaseOrder}
 * gives: a row that ends up moving stock and money is never mass-assigned from a request.
 * `SavePurchaseReturn` and (in the next slice) `CompletePurchaseReturn` are the only things that
 * write one, and they name every column.
 *
 * **How much of a delivery is still returnable is not a column here.** It is a question about
 * every return against that order, and {@see ReturnedQuantities} is the one place it is asked. A
 * denormalised counter would make this class a second writer of `purchase_order_items`, and
 * every path — save, complete, cancel, delete, edit down — would have to keep it true.
 *
 * @property int $id
 * @property string $number
 * @property int $purchase_order_id
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
 * @property-read PurchaseOrder $purchaseOrder
 * @property-read Collection<int, PurchaseReturnItem> $items
 * @property-read User|null $creator
 */
class PurchaseReturn extends Model
{
    use SoftDeletes;

    /**
     * What searching returns means.
     *
     * The return's own number first, because that is what somebody is holding. Then the order's
     * number, which is the other piece of paper on the desk — a return is looked for by the
     * delivery it came off at least as often as by itself. Then the supplier's name, and the
     * notes.
     *
     * **Status and reason are deliberately not searched.** Both are codes with words in three
     * languages, so matching English would work in one locale only — which is what the two
     * filters above the list are for.
     *
     * **The lines are not searched either**, the same call {@see PurchaseOrder::search()} makes:
     * a term matching a common material would return most of the list, which is not a search.
     *
     * @param  Builder<PurchaseReturn>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('number', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('purchaseOrder', fn (Builder $order) => $order->where('number', 'like', $like))
                ->orWhereHas('purchaseOrder.supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like));
        });
    }

    /**
     * The delivery being credited.
     *
     * `withTrashed`, for the reason the order's own supplier relation is: a return whose whole
     * identity is "what this credits" must still be able to say so. In practice it cannot
     * happen — a received order is refused by `PurchaseOrderController::destroy()` and so is
     * never soft-deleted — which is exactly why the relation should not be the thing that breaks
     * if that ever changes.
     *
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    /** @return HasMany<PurchaseReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Money and rates read back as strings, never floats — the rule {@see PurchaseOrder} states
     * in full. `decimal:6` on the rate alone, because its column is.
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
