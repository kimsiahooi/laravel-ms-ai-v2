<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\FulfillSalesOrder;
use App\Actions\OpenSalesOrder;
use App\Enums\SalesOrderStatus;
use App\Http\Requests\Tenant\SalesOrderRequest;
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
 * Goods sold to a customer — see the migration for why the ledger alone cannot say this,
 * and why every figure on it is stored rather than derived.
 *
 * Mutable, but only while it is {@see SalesOrderStatus::Pending}: an order is amended and
 * re-quoted before anything ships, and both other states are terminal. Every check in this
 * module reduces to `status === Pending`.
 *
 * No `#[Fillable]` and `$guarded` left at its default, deliberately, for the reason
 * {@see StockMovement} gives: a row that ends up moving stock and money is never
 * mass-assigned from a request. v1 made `status`, `number` and the fulfilment columns all
 * fillable and passed the request array straight through, which put the entire lifecycle one
 * crafted payload away from being skipped. {@see OpenSalesOrder} and
 * {@see FulfillSalesOrder} are the only things that write one, and they name every column.
 *
 * The `creator` relation is spelled out rather than taken from {@see RecordsCreator}: the
 * trait stamps whoever happens to be authenticated at `creating` time, and an order has two
 * actors written at two different moments.
 *
 * @property int $id
 * @property string $number
 * @property int|null $customer_id
 * @property SalesOrderStatus $status
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
 * @property int|null $fulfilled_by
 * @property Carbon|null $fulfilled_at
 * @property int|null $fulfilled_warehouse_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, SalesOrderItem> $items
 * @property-read User|null $creator
 * @property-read User|null $fulfiller
 * @property-read Warehouse|null $fulfilledWarehouse
 */
class SalesOrder extends Model
{
    use SoftDeletes;

    /**
     * What "find a sales order" means: the number on it, who it was sold to, and whatever
     * was written down about it.
     *
     * The number first, because that is what somebody is holding when they come looking —
     * a customer quotes it on the phone, and it is the one string on the document that
     * identifies it. v1 searched the raw `id`, which nobody outside the database has seen.
     *
     * The status is deliberately not searched: it is a code like `pending`, and matching
     * English against it would work in one locale and silently not in the other two. The
     * status filter is the control for that question.
     *
     * The lines are not searched either. An order is remembered by who it was for and what
     * it was called, not by one of forty products on it — and a term matching a common
     * product would return most of the list, which is a filter behaving like a search box.
     *
     * @param  Builder<SalesOrder>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('number', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like));
        });
    }

    /**
     * Who this was sold to.
     *
     * `withTrashed`, because an archived customer is still the customer this order was taken
     * from — and a sales order that cannot name its counterparty is not an accounting
     * record. Null only once the customer row has been hard-deleted, which the FK turns into
     * a null rather than taking the order with it.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return HasMany<SalesOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * Who took the order. Null for one created by a console command or a seeder.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who shipped the goods — a different person from the one who took the order often
     * enough that v1's single column lost the answer. Null until the order is fulfilled.
     *
     * @return BelongsTo<User, $this>
     */
    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    /**
     * Which warehouse the goods left. Null until fulfilled.
     *
     * `withTrashed`, like the customer: a warehouse closed since the despatch is still where
     * the despatch came from, and the movements it produced are attached to it under
     * `restrictOnDelete` anyway.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function fulfilledWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'fulfilled_warehouse_id')->withTrashed();
    }

    /**
     * Money and rates read back as strings, never floats.
     *
     * `decimal:4` for everything this order stores as money, matching the columns and the
     * scale {@see Money} works at, so a stored figure can be handed straight back to bcmath.
     * `exchange_rate` gets `decimal:6` because its column does — an FX rate is quoted more
     * precisely than the money it converts.
     *
     * v1 cast none of the totals, because it stored none of them: they were floats computed
     * in a DTO on every read. Casting one of these to a float anywhere — including a
     * `(float)` on the way to the browser — reintroduces exactly the drift the fixed-point
     * columns exist to prevent.
     *
     * `expected_date` is cast like `fulfilled_at` but is **not** an instant: it holds the
     * promised delivery date exactly as chosen, read and written as a wall clock with no
     * zone anywhere near it. The cast is here only so it arrives as a `Carbon` to be
     * formatted from; nothing converts it. See the column comment and
     * {@see SalesOrderRequest::expectedInstant()}.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'exchange_rate' => 'decimal:6',
            'tax_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
            'expected_date' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }
}
