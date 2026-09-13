<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\FulfillSalesOrder;
use App\Data\StockAvailabilityData;
use App\Enums\StockItemType;
use App\Http\Controllers\Tenant\SalesOrderController;
use App\Models\Product;
use App\Models\SalesOrderItem;
use App\Services\StockService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What a sales order needs of each product, and whether one warehouse can cover it.
 *
 * **Two readers, one answer.** {@see SalesOrderController::show()} draws the panel that says
 * what the chosen warehouse holds; {@see FulfillSalesOrder} decides whether the despatch
 * happens at all. Those are the same question asked a second apart, and asking it twice in two
 * files is how a screen ends up showing a green row for a line the server then refuses. So the
 * addition and the comparison live here and both callers pass through them — the only thing
 * that differs is where the levels came from, which is exactly the thing that *should* differ:
 * the panel reads them unlocked ({@see StockService::onHandFor()}) and the Action reads them
 * under `FOR UPDATE` ({@see StockService::lockLevels()}).
 *
 * **Per product, added across lines.** There is no unique index on (order, product) — two
 * lines of the same product at two prices is an ordinary quotation — so five and five against
 * eight on the shelf must be refused, and a per-line check passes both of them. This is the
 * single place that addition happens.
 *
 * A line whose product has been hard-deleted is skipped, in both directions. It issues no
 * stock, so it raises no availability question; `ReceivePurchaseOrder` makes the same call
 * about a deleted material, and for the same reason.
 */
final class OrderAvailability
{
    /** The scale of `decimal(15,4)` — the quantity columns', and {@see StockService}'s. */
    private const SCALE = 4;

    /**
     * How much of each product the whole order comes to, keyed by {@see StockItem::key()}.
     *
     * The product model travels with the quantity because every caller needs both — one to
     * lock its level row, the other to name it on screen — and re-fetching it from the key
     * would be a second query for a row already in hand.
     *
     * Insertion order is first-appearance order, which is the order the lines are in, which is
     * the order the person entering them arranged. Nothing re-sorts it; the panel reads down
     * the document.
     *
     * @param  Collection<int, SalesOrderItem>  $lines  with `product` eager-loaded, or this
     *                                                  is a query per line
     * @return array<string, array{product: Product, quantity: numeric-string}>
     */
    public static function required(Collection $lines): array
    {
        $required = [];

        foreach ($lines as $line) {
            $product = $line->product;

            // Named rather than tested as a bare Model: `product_id` is nullable and a hard
            // delete nulls it, which is a real outcome rather than an analyser formality.
            if (! $product instanceof Product) {
                continue;
            }

            $key = StockItem::key(StockItemType::Product->value, $product->id);
            $quantity = self::numeric((string) $line->quantity);

            $required[$key] = [
                'product' => $product,
                'quantity' => isset($required[$key])
                    ? bcadd($required[$key]['quantity'], $quantity, self::SCALE)
                    : $quantity,
            ];
        }

        return $required;
    }

    /**
     * Those requirements against a warehouse's levels, one row per product.
     *
     * A key the levels do not carry is zero on hand — see {@see StockService::onHandFor()} on
     * why an item a warehouse has never held has no row rather than a row of zero.
     *
     * @param  array<string, array{product: Product, quantity: numeric-string}>  $required  from {@see required()}
     * @param  array<string, numeric-string>  $levels  from `onHandFor()` or `lockLevels()`
     * @return list<StockAvailabilityData>
     */
    public static function rows(array $required, array $levels): array
    {
        $rows = [];

        foreach ($required as $key => $need) {
            $rows[] = StockAvailabilityData::for(
                $need['product'],
                $need['quantity'],
                $levels[$key] ?? bcadd('0', '0', self::SCALE),
            );
        }

        return $rows;
    }

    /**
     * Just the rows that cannot be covered.
     *
     * Separate from {@see rows()} rather than a flag on it, because the two callers want
     * opposite halves: the panel shows everything and marks the short ones, and the Action
     * only ever asks whether this list is empty.
     *
     * @param  list<StockAvailabilityData>  $rows
     * @return list<StockAvailabilityData>
     */
    public static function shortfalls(array $rows): array
    {
        return array_values(array_filter($rows, static fn (StockAvailabilityData $row): bool => $row->short));
    }

    /**
     * The models whose level rows a caller has to read or lock.
     *
     * @param  array<string, array{product: Product, quantity: numeric-string}>  $required
     * @return list<Product>
     */
    public static function products(array $required): array
    {
        return array_values(array_map(
            static fn (array $need): Product => $need['product'],
            $required,
        ));
    }

    /**
     * A quantity, proven to be a number, for the reason {@see StockService::decimal()} gives:
     * bcmath throws a `ValueError` naming its own argument rather than the row it came from.
     *
     * Unreachable — `sales_order_items.quantity` is `decimal(15,4)` and cast to match — and it
     * throws rather than falling back to zero anyway. A zero here would understate what the
     * order needs, which is the one direction this file must never be wrong in: it would let
     * a despatch through that the shelf cannot cover.
     *
     * @return numeric-string
     */
    private static function numeric(string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new InvalidArgumentException(
                sprintf('Order line quantities must be numeric, got "%s".', $quantity),
            );
        }

        return $quantity;
    }
}
