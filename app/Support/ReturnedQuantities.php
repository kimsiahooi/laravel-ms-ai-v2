<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\CompletePurchaseReturn;
use App\Actions\CompleteSalesReturn;
use App\Data\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnStatus;
use App\Enums\SalesOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturnItem;
use App\Models\SalesOrder;
use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * How much of a traded line has already gone back, and how much may still.
 *
 * **This is the ceiling the whole returns module exists for.** v1 had none on either side. A
 * purchase return's only check was that a material had appeared on *some* received order from
 * the chosen supplier, so ten thousand could go back against a receipt of five, repeatedly; a
 * sales return had no check at all, which made it an unbounded stock-in that could add inventory
 * on demand. `traded − already returned` is the real answer, and it is only answerable because a
 * return names the order line it credits.
 *
 * **One class for both directions.** The arithmetic, the zero floor and the numeric guard are
 * identical whichever way the goods travel, and the only thing that genuinely differs is which
 * pair of tables holds the lines — so that is the only thing the two query builders below do
 * differently. The alternative, a `SalesReturnedQuantities` sibling, would be a second copy of
 * the subtle parts (the fold, the floor, the soft-delete behaviour) to keep in step by hand.
 *
 * **Asked in two places with two different questions, on both sides.** The form and the
 * FormRequest ask "is this a sensible document to raise", which counts pending siblings — see
 * {@see ReturnStatus::consuming()}. {@see CompletePurchaseReturn} and {@see CompleteSalesReturn}
 * ask the narrower "can this be done right now", which counts only what has actually moved. Same
 * arithmetic, different `$statuses`, and the difference is deliberate rather than an oversight.
 *
 * **A computed aggregate, not a denormalised column.** A `returned_quantity` on the order-item
 * tables would make every path — save, complete, cancel, delete, edit down, restore — a second
 * writer of a table only the order Actions write today, and one missed path is a permanently
 * wrong ceiling with no symptom. The question is only ever asked about one order at a time, so
 * there is no N+1 to avoid.
 *
 * **Folded with `bcadd`, not `SUM()`.** A `SUM()` over a `decimal` comes back as a string on
 * MySQL and as a float elsewhere, and the float is the version that loses the fourth place on
 * exactly the quantity somebody checks by hand. `selectRaw` is also banned outright in a
 * controller by `scripts/check-structure.sh`. The row count is bounded by an order's line count
 * times the returns against it — tens.
 */
final class ReturnedQuantities
{
    /** The scale of `decimal(15,4)` — the quantity columns', and {@see StockService}'s. */
    private const SCALE = 4;

    /**
     * How much of each *purchase* order line has been returned, keyed by
     * `purchase_order_items.id`.
     *
     * **A key that is absent means none**, rather than zero — the caller reads it as `?? '0'`,
     * which is the same shape {@see StockService::onHandFor()} uses for a warehouse that has
     * never held an item.
     *
     * **Soft-deleted returns fall out on their own, and that absence is load-bearing.**
     * `whereHas` builds its subquery from the relation, so `PurchaseReturn`'s `SoftDeletes`
     * global scope rides along and a deleted return releases the quantity it was holding.
     * {@see PurchaseReturnItem::purchaseReturn()} must therefore never gain `withTrashed()` —
     * it says so there too, because this is the kind of thing that gets "tidied up" a year
     * later. The same applies verbatim to {@see SalesReturnItem::salesReturn()}.
     *
     * @param  list<int>  $orderItemIds
     * @param  int|null  $excluding  a return not to count against itself — the whole of the
     *                               edit path's correctness. Without it, editing a return from
     *                               5 down to 3 is refused by its own existing 5.
     * @param  list<ReturnStatus>|null  $statuses  which returns consume the line. Defaults to
     *                                             {@see ReturnStatus::consuming()}; the
     *                                             completion path passes the narrower set.
     * @return array<int, numeric-string>
     */
    public static function forPurchaseOrderItems(array $orderItemIds, ?int $excluding = null, ?array $statuses = null): array
    {
        // `whereIn(…, [])` is a valid query that returns nothing, but it is still a round trip
        // for a question with no rows in it — and the create path would run it on an order with
        // no lines every render.
        if ($orderItemIds === []) {
            return [];
        }

        $lines = PurchaseReturnItem::query()
            ->whereIn('purchase_order_item_id', $orderItemIds)
            ->whereHas('purchaseReturn', self::consumedBy($excluding, $statuses))
            ->get(['purchase_order_item_id', 'quantity']);

        return self::fold($lines, 'purchase_order_item_id');
    }

    /**
     * The same fold on the purchase side, counting only returns that have actually moved goods.
     *
     * **The narrower of the two ceilings**, and the only one that may refuse an irreversible
     * step. A pending sibling has taken nothing off a shelf, so it must not stand between this
     * return and its completion — but it does count when somebody is *raising* a document.
     *
     * **No `$excluding`, deliberately.** {@see CompletePurchaseReturn} holds an exclusive lock on
     * the return and has just proved it is still `Pending`, so it cannot be in the `Completed`
     * set and cannot count against itself. Passing its id would be a guard against a state that
     * cannot exist, and one more argument to get wrong.
     *
     * **Read without a lock, and that is the correct call rather than a missing one.** The caller
     * holds the parent order's row, which every competing completion must also hold, so the
     * `Completed` set cannot change underneath this read — see {@see CompletePurchaseReturn},
     * which explains why a `FOR UPDATE` here would deadlock against
     * {@see SavePurchaseReturn::revise()} while protecting nothing that can happen.
     *
     * @param  list<int>  $orderItemIds
     * @return array<int, numeric-string>
     */
    public static function completedForPurchaseOrderItems(array $orderItemIds): array
    {
        return self::forPurchaseOrderItems($orderItemIds, null, [ReturnStatus::Completed]);
    }

    /**
     * How much of each *sales* order line has come back, keyed by `sales_order_items.id`.
     *
     * The mirror of {@see forPurchaseOrderItems()}, and every note on it applies unchanged —
     * including the one about soft deletes, which is why {@see SalesReturnItem::salesReturn()}
     * must never gain `withTrashed()` either.
     *
     * @param  list<int>  $orderItemIds
     * @param  int|null  $excluding  the return not to count against itself
     * @param  list<ReturnStatus>|null  $statuses  defaults to {@see ReturnStatus::consuming()}
     * @return array<int, numeric-string>
     */
    public static function forSalesOrderItems(array $orderItemIds, ?int $excluding = null, ?array $statuses = null): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        $lines = SalesReturnItem::query()
            ->whereIn('sales_order_item_id', $orderItemIds)
            ->whereHas('salesReturn', self::consumedBy($excluding, $statuses))
            ->get(['sales_order_item_id', 'quantity']);

        return self::fold($lines, 'sales_order_item_id');
    }

    /**
     * The completed-only ceiling on the sales side — what {@see CompleteSalesReturn} asks under
     * the parent order's lock. See {@see completedForPurchaseOrderItems()} for the whole
     * argument; it is identical, and so is the reason this read takes no lock of its own.
     *
     * @param  list<int>  $orderItemIds
     * @return array<int, numeric-string>
     */
    public static function completedForSalesOrderItems(array $orderItemIds): array
    {
        return self::forSalesOrderItems($orderItemIds, null, [ReturnStatus::Completed]);
    }

    /**
     * `traded − returned`, never below zero, always at the column's own scale.
     *
     * **The floor is not defensive tidiness.** A line over-returned by data written before this
     * rule existed would otherwise produce a negative ceiling, and a negative ceiling refuses
     * every quantity — including the correction somebody is trying to make, which is exactly
     * when they need the screen most.
     *
     * Both operands are guarded here rather than at every call site: what callers hold is a
     * `decimal:4` cast, which is a `string` that nothing proves is a number — see
     * {@see numeric()}.
     *
     * @return numeric-string
     */
    public static function remaining(string $traded, string $returned): string
    {
        $left = bcsub(self::numeric($traded), self::numeric($returned), self::SCALE);

        return bccomp($left, '0', self::SCALE) > 0 ? $left : bcadd('0', '0', self::SCALE);
    }

    /**
     * Whether anything on this document can still go back.
     *
     * What the "Return items" button on an order's own page asks, on both sides. It
     * short-circuits on a document that has not shipped or arrived yet, so the query only runs
     * on the page that can act on the answer — and it stays off {@see PurchaseOrderData} and its
     * sales counterpart, which also feed twenty-row lists where a ceiling query per row would be
     * the v1 shape this module exists to undo.
     *
     * One method over a union rather than two: "can anything still go back" is one question, and
     * the only part of answering it that differs is which status counts as traded and which fold
     * to ask.
     */
    public static function hasReturnable(PurchaseOrder|SalesOrder $order): bool
    {
        $traded = $order instanceof PurchaseOrder
            ? $order->status === PurchaseOrderStatus::Received
            : $order->status === SalesOrderStatus::Fulfilled;

        if (! $traded) {
            return false;
        }

        $lines = $order->items()->get(['id', 'quantity']);
        $ids = array_values($lines->map(static fn (Model $line): int => (int) $line->getKey())->all());

        $returned = $order instanceof PurchaseOrder
            ? self::forPurchaseOrderItems($ids)
            : self::forSalesOrderItems($ids);

        foreach ($lines as $line) {
            $left = self::remaining(
                (string) $line->getAttribute('quantity'),
                $returned[(int) $line->getKey()] ?? '0',
            );

            if (bccomp($left, '0', self::SCALE) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which returns count against a line — the half of the query that is the same on both sides.
     *
     * Expressed as a closure over the *document* rather than a filter on the line, because the
     * document is the thing being excluded and the thing whose status decides.
     *
     * @param  list<ReturnStatus>|null  $statuses
     * @return \Closure(Builder<covariant Model>): void
     */
    private static function consumedBy(?int $excluding, ?array $statuses): \Closure
    {
        return function (Builder $return) use ($excluding, $statuses): void {
            $return->whereIn('status', $statuses ?? ReturnStatus::consuming());

            if ($excluding !== null) {
                $return->whereKeyNot($excluding);
            }
        };
    }

    /**
     * The rows added up per order line — the arithmetic both sides share.
     *
     * Templated over the line type rather than typed as `Collection<int, Model>`, because
     * Eloquent's collection is not covariant: a `Collection<int, PurchaseReturnItem>` is not a
     * `Collection<int, Model>` to the analyser, and widening the parameter would be hiding the
     * question rather than answering it. The same technique {@see OrderAvailability::demandsFrom()}
     * uses for the same reason.
     *
     * @template TItem of Model
     *
     * @param  Collection<int, TItem>  $rows
     * @return array<int, numeric-string>
     */
    private static function fold(Collection $rows, string $column): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $key = (int) $row->getAttribute($column);

            $totals[$key] = bcadd(
                $totals[$key] ?? '0',
                self::numeric((string) $row->getAttribute('quantity')),
                self::SCALE,
            );
        }

        return $totals;
    }

    /**
     * A quantity, proven to be a number, for the reason {@see OrderAvailability::numeric()}
     * gives: a `decimal:4` cast hands back a `string` and nothing about it promises bcmath an
     * operand, which throws a `ValueError` naming its own argument rather than the row.
     *
     * Unreachable — every quantity column here is `decimal(15,4)` — and it throws rather than
     * falling back to zero anyway. A zero would *overstate* what is still returnable, which is
     * the one direction this file must never be wrong in.
     *
     * @return numeric-string
     */
    private static function numeric(string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new InvalidArgumentException(
                sprintf('Return quantities must be numeric, got "%s".', $quantity),
            );
        }

        return $quantity;
    }
}
