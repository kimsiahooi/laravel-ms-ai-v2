<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\OpenSalesOrder;
use App\Data\SalesOrderItemData;
use App\Enums\DiscountType;
use App\Services\StockService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a sales order — see the migration for why the price is the point of the row,
 * why the same product may appear on two of them, and what that costs fulfilment.
 *
 * No soft delete: a line only exists inside an order, the order's own soft delete covers
 * changing your mind about the whole thing, and {@see OpenSalesOrder} replaces the lines
 * wholesale when a pending order is edited. Nothing here outlives its parent.
 *
 * No `#[Fillable]` and `$guarded` left at its default, for the reason {@see StockMovement}
 * gives. `line_total` is the column that makes it matter: it is arithmetic the server
 * performed, not a number anybody may send, and a fillable list here could only widen what a
 * request can reach.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int|null $product_id
 * @property string $quantity
 * @property string $unit_price
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property bool $taxable
 * @property string $line_total computed by OrderTotals::line(), never sent by a client
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read SalesOrder $salesOrder
 * @property-read Product|null $product
 */
class SalesOrderItem extends Model
{
    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * What was sold.
     *
     * `withTrashed`, and this relation is what replaces v1's snapshot blob. An order outlives
     * the catalogue's tidying: a product archived after the order was raised must still name
     * itself on the document, and it does, because the archive is a soft delete and this
     * relation ignores it. Null only after a hard delete, which the FK turns into a null —
     * and a line that can no longer say what it was for still says what was charged, which is
     * the half an accounting record cannot lose.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Every number reads back as a string, never a float.
     *
     * See {@see StockService} on why the whole engine works in decimal strings, and
     * {@see Money} on why money in particular does; `decimal:4` is what keeps that true on
     * the way back out of the database, so a quantity or a price can be handed straight to
     * bcmath at the scale its column stores.
     *
     * v1 cast `quantity` and `unit_price` the same way and then read them back into a `float`
     * DTO two lines later, which threw the benefit away — see {@see SalesOrderItemData} for
     * what travels instead.
     *
     * @return array<string, string>
     */
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
