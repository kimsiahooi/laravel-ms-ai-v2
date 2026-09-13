<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * How much of a delivered line has already gone back, and how much may still.
 *
 * **This is the ceiling the whole module exists for.** v1 had none: its only check was that a
 * material had appeared on *some* received order from the chosen supplier, so ten thousand could
 * be returned against a receipt of five, repeatedly. `ordered − already returned` is the real
 * answer, and it is only answerable because a return names the order line it credits.
 *
 * **Asked in two places with two different questions, and both come through here.** The form and
 * the FormRequest ask "is this a sensible document to raise", which counts pending siblings —
 * see {@see ReturnStatus::consuming()}. The completion Action will ask the narrower "can this be
 * done right now", which counts only what has actually moved. Same arithmetic, different
 * `$statuses`, and the difference is deliberate rather than an oversight.
 *
 * **A computed aggregate, not a denormalised column.** A `returned_quantity` on
 * `purchase_order_items` would make every path — save, complete, cancel, delete, edit down,
 * restore — a second writer of a table only `OpenPurchaseOrder` writes today, and one missed
 * path is a permanently wrong ceiling with no symptom. The question is only ever asked about one
 * order at a time, so there is no N+1 to avoid.
 *
 * **Folded with `bcadd`, not `SUM()`.** A `SUM()` over a `decimal` comes back as a string on
 * MySQL and as a float elsewhere, and the float is the version that loses the fourth place on
 * exactly the quantity somebody checks by hand. `selectRaw` is also banned outright in a
 * controller by `scripts/check-structure.sh`, and this is a helper two modules will share. The
 * row count is bounded by an order's line count times the returns against it — tens.
 */
final class ReturnedQuantities
{
    /** The scale of `decimal(15,4)` — the quantity columns', and {@see StockService}'s. */
    private const SCALE = 4;

    /**
     * How much of each order line has been returned, keyed by `purchase_order_items.id`.
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
     * later.
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
    public static function forOrderItems(array $orderItemIds, ?int $excluding = null, ?array $statuses = null): array
    {
        // `whereIn(…, [])` is a valid query that returns nothing, but it is still a round trip
        // for a question with no rows in it — and the create path would run it on an order with
        // no lines every render.
        if ($orderItemIds === []) {
            return [];
        }

        $lines = PurchaseReturnItem::query()
            ->whereIn('purchase_order_item_id', $orderItemIds)
            ->whereHas('purchaseReturn', function (Builder $return) use ($excluding, $statuses): void {
                $return->whereIn('status', $statuses ?? ReturnStatus::consuming());

                if ($excluding !== null) {
                    // Expressed against the document rather than as a filter on the line,
                    // because the document is the thing being excluded.
                    $return->whereKeyNot($excluding);
                }
            })
            ->get(['purchase_order_item_id', 'quantity']);

        $totals = [];

        foreach ($lines as $line) {
            $key = $line->purchase_order_item_id;

            $totals[$key] = bcadd($totals[$key] ?? '0', self::numeric($line->quantity), self::SCALE);
        }

        return $totals;
    }

    /**
     * `ordered − returned`, never below zero, always at the column's own scale.
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
    public static function remaining(string $ordered, string $returned): string
    {
        $left = bcsub(self::numeric($ordered), self::numeric($returned), self::SCALE);

        return bccomp($left, '0', self::SCALE) > 0 ? $left : bcadd('0', '0', self::SCALE);
    }

    /**
     * Whether anything on this order can still go back.
     *
     * What the "Return items" button on the order's own page asks. It short-circuits on a
     * non-received order, so the query only runs on the page that can act on the answer — and
     * it stays off {@see PurchaseOrderData}, which also feeds a twenty-row list where
     * a ceiling query per row would be the v1 shape this module exists to undo.
     */
    public static function hasReturnable(PurchaseOrder $order): bool
    {
        if ($order->status !== PurchaseOrderStatus::Received) {
            return false;
        }

        $lines = $order->items()->get(['id', 'quantity']);
        $ids = array_values($lines->map(static fn (PurchaseOrderItem $line): int => $line->id)->all());
        $returned = self::forOrderItems($ids);

        foreach ($lines as $line) {
            $left = self::remaining($line->quantity, $returned[$line->id] ?? '0');

            if (bccomp($left, '0', self::SCALE) > 0) {
                return true;
            }
        }

        return false;
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
