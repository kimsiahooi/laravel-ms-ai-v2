<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\OpenPurchaseOrder;
use App\Actions\ReceivePurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\Concerns\RecordsCreator;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Goods ordered from a supplier — see the migration for why the ledger alone cannot say
 * this, and why every figure on it is stored rather than derived.
 *
 * Mutable, but only while it is {@see PurchaseOrderStatus::Pending}: an order is
 * negotiated, corrected and re-sent before anything ships, and both other states are
 * terminal. Every check in this module reduces to `status === Pending`.
 *
 * No `#[Fillable]` and `$guarded` left at its default, deliberately, for the reason
 * {@see StockMovement} gives: a row that ends up moving stock and money is never
 * mass-assigned from a request. v1 made `status`, `number`, `received_at` and
 * `received_warehouse_id` all fillable, which put the entire lifecycle one crafted
 * payload away from being skipped. {@see OpenPurchaseOrder} and
 * {@see ReceivePurchaseOrder} are the only things that write one, and they name every
 * column.
 *
 * The `creator` relation is spelled out here rather than taken from
 * {@see RecordsCreator}: the trait stamps whoever happens to be authenticated at
 * `creating` time, and an order has two actors written at two different moments. Naming
 * both columns at the point they are set keeps them symmetric and keeps a
 * console-created order honestly authorless.
 *
 * @property int $id
 * @property string $number
 * @property int|null $supplier_id
 * @property PurchaseOrderStatus $status
 * @property string $currency
 * @property string $exchange_rate
 * @property string $tax_rate the percentage this order was raised under, snapshotted
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property string|null $notes
 * @property Carbon|null $expected_date
 * @property int|null $created_by
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $received_warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read User|null $creator
 * @property-read User|null $receiver
 * @property-read Warehouse|null $receivedWarehouse
 */
class PurchaseOrder extends Model
{
    use SoftDeletes;

    /**
     * What "find a purchase order" means: the number on it, whoever it was placed with,
     * and whatever was written down about it.
     *
     * The number first, because that is what a person is holding when they come looking —
     * a supplier's invoice quotes it, and it is the one string on the document that
     * identifies it. v1 searched the raw `id` instead, which is a number nobody outside
     * the database has ever seen.
     *
     * The status is deliberately not searched: it is a code like `pending`, and matching
     * English against it would work in one locale and silently not in the other two. The
     * status filter is the control for that question.
     *
     * The lines are not searched either. An order is remembered by who it was with and
     * what it was called, not by one of forty materials on it — and a term matching a
     * common material would return most of the list, which is a filter behaving like a
     * search box.
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('number', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', $like));
        });
    }

    /**
     * Who this was ordered from.
     *
     * `withTrashed`, because an archived supplier is still the supplier this order was
     * placed with — and a purchase order that cannot name its counterparty is not an
     * accounting record. Null only once the supplier row has been hard-deleted, which
     * the FK turns into a null rather than taking the order with it.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Who raised the order. Null for one created by a console command or a seeder.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who booked the goods in — a different person from the one who ordered them often
     * enough that v1's single column lost the answer. Null until the order is received.
     *
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Where the goods landed. Null until received.
     *
     * `withTrashed`, like the supplier: a warehouse closed since the delivery is still
     * where the delivery went, and the movements it produced are attached to it under
     * `restrictOnDelete` anyway.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function receivedWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'received_warehouse_id')->withTrashed();
    }

    /**
     * Money and rates read back as strings, never floats.
     *
     * `decimal:4` for everything the order stores as money, matching the columns and the
     * scale {@see Money} works at, so a stored figure can be handed straight
     * back to bcmath. `exchange_rate` gets `decimal:6` because its column does — an FX
     * rate is quoted more precisely than the money it converts.
     *
     * v1 cast none of the totals, because it stored none of them: they were floats
     * computed in a DTO on every read. Casting one of these to a float anywhere —
     * including a `(float)` on the way to the browser — reintroduces exactly the drift
     * the fixed-point columns exist to prevent.
     *
     * `expected_date` is a `datetime` like `received_at`, and holds an instant in UTC.
     * The screen still asks for a day; the day is anchored to its start in the zone the
     * person picking it was in — see {@see PurchaseOrderRequest}
     * — so they read back the date they chose.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'exchange_rate' => 'decimal:6',
            'tax_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'expected_date' => 'datetime',
            'received_at' => 'datetime',
        ];
    }
}
