<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ReturnExceedsOrderException;
use App\Http\Requests\Tenant\SalesReturnRequest;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Takes the goods back onto a shelf: one movement per line, into one warehouse.
 *
 * The mirror of {@see CompletePurchaseReturn}, and the one place the two stop mirroring is the
 * direction. A purchase return is a subtraction and the shelf is allowed to say no; this is an
 * addition and it cannot fail for want of stock. So there is **no availability check and no
 * shortfall exception here**, and the warehouse picker on the screen has no panel under it.
 *
 * **What can still refuse it is the ceiling**, and that is the whole reason this Action is more
 * than three lines. {@see SalesReturnRequest} refuses an over-return at save time counting
 * *pending* siblings too; here the question is only "can this be done right now", so a pending
 * sibling counts for nothing — it has put nothing on a shelf. See
 * {@see ReturnedQuantities::completedForSalesOrderItems()}.
 *
 * ## The lock order, and why every step of it is where it is
 *
 *     sales_returns row  →  sales_orders row  →  [ceiling read]  →  warehouse_stocks rows
 *
 * Identical to its opposite number, and load-bearing for identical reasons.
 *
 * **The return's own row** is the double-press guard. **The parent order's row is the
 * rendezvous** — not merely because two returns against one despatch are two different rows, but
 * because the ceiling's `status = completed` test lives inside a `whereHas`, which compiles to a
 * correlated `EXISTS`, and MySQL is explicit that a locking read in an outer statement does not
 * lock the rows of a table in a nested subquery. That predicate is therefore answered by a
 * consistent read whatever the outer statement asks for, and under REPEATABLE READ a consistent
 * read answers from the transaction's read view — so the only way to make that read view late
 * enough is to hold, before it is created, a lock every competing completion must also have held.
 *
 * **So the invariant is: nothing in this transaction reads without a lock until the order row is
 * held.** Steps one and two are locking reads, which take the latest committed row and do not
 * establish a read view; the first plain read comes after both.
 *
 * **The ceiling read itself is deliberately not `FOR UPDATE`**, for the two reasons
 * {@see CompletePurchaseReturn} sets out: it would protect against nothing reachable, and it
 * would deadlock against {@see SaveSalesReturn::revise()}, which locks the same clustered rows
 * through the other index.
 *
 * **The level rows are locked anyway, and their return value is deliberately unused.** Nothing
 * here can run short, so there is nothing to check — but two documents putting stock into the
 * same warehouse that overlap on two products would otherwise each hold the row the other needs.
 * {@see StockService::lockLevels()} takes them in one canonical order, which is what makes that
 * impossible. The same call, for the same reason, {@see ReceivePurchaseOrder} makes.
 */
final class CompleteSalesReturn
{
    /** The scale of `decimal(15,4)` — the quantity columns', and {@see ReturnedQuantities}'s. */
    private const SCALE = 4;

    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws ReturnExceedsOrderException when a sibling return completed first and the despatch
     *                                     no longer has room for this one. Nothing is written,
     *                                     and the return is left pending and still editable.
     * @throws InsufficientStockException declared because {@see StockService::record()} declares
     *                                    it, and unreachable twice over: this only ever adds,
     *                                    and every level row is held from before the write.
     * @throws DomainException when the return stopped being pending between the controller's
     *                         check and this lock — the true double-press race.
     */
    public function handle(SalesReturn $return, Warehouse $warehouse, ?User $user = null): SalesReturn
    {
        return DB::transaction(function () use ($return, $warehouse, $user): SalesReturn {
            $locked = SalesReturn::query()->whereKey($return->getKey())->lockForUpdate()->first();

            // Already completed, already cancelled, or deleted from under us — this model soft
            // deletes, so `null` is a real answer rather than an impossible one. The ordinary
            // press against a non-pending return never reaches here; the controller refuses it
            // first with a sentence a person can read.
            if ($locked === null || $locked->status !== ReturnStatus::Pending) {
                throw new DomainException('Sales return is no longer pending.');
            }

            // The rendezvous. `withTrashed()` because the relation this mirrors declares it and
            // the intent belongs on the page — though a fulfilled order cannot in fact be
            // trashed, since `SalesOrderController::destroy()` refuses anything but a pending
            // one. Nothing re-checks that the order is still `Fulfilled`, and nothing needs to:
            // there is no route that un-fulfils one. It is locked to be held, not to be read.
            SalesOrder::query()
                ->withTrashed()
                ->whereKey($locked->sales_order_id)
                ->lockForUpdate()
                ->first();

            // The first plain read of the transaction, and therefore where its read view begins
            // — after both locks, which is the whole argument above. Two hops to the product,
            // where a sales order's own line is one, so the eager load is not optional.
            $lines = $locked->items()->with('salesOrderItem.product')->get();

            $this->refuseOverReturn($lines);

            // Held rather than read: see the class note on why a document that cannot run short
            // still takes every level row up front.
            $this->stock->lockLevels($warehouse, self::products($lines));

            foreach ($lines as $line) {
                $this->receiveLine($locked, $line, $warehouse, $user);
            }

            $locked->forceFill([
                'status' => ReturnStatus::Completed,
                'completed_at' => now(),
                // Not the creator: the person who agrees a credit note is routinely not the
                // person who puts the box back on the shelf.
                'completed_by' => $user?->id,
                'completed_warehouse_id' => $warehouse->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * Refuse the whole document if any line no longer fits the despatch.
     *
     * **Compared line by line, where a shelf check would add up.** That asymmetry is the same one
     * {@see CompletePurchaseReturn::refuseOverReturn()} explains: `sales_return_items_line_unique`
     * guarantees one row per (return, order line), so a line is the whole of this return's claim
     * on that order line and there is nothing to add together.
     *
     * **Every line that no longer fits, not the first**, so one press reveals the whole answer
     * rather than the next thing wrong with it.
     *
     * @param  Collection<int, SalesReturnItem>  $lines
     *
     * @throws ReturnExceedsOrderException
     */
    private function refuseOverReturn(Collection $lines): void
    {
        $ids = array_values(array_unique(
            $lines->map(static fn (SalesReturnItem $line): int => $line->sales_order_item_id)->all(),
        ));

        $completed = ReturnedQuantities::completedForSalesOrderItems($ids);

        $exceeded = [];

        foreach ($lines as $line) {
            $source = $line->salesOrderItem;

            $left = ReturnedQuantities::remaining(
                $source->quantity,
                $completed[$line->sales_order_item_id] ?? '0',
            );

            // `remaining()` read the other way round: how much of this line has nowhere to go.
            // It guards both operands and floors at zero, so a line that fits answers exactly
            // zero and a line that does not answers by how much — which avoids another copy of
            // the numeric guard and a bare `bccomp` on a `decimal:4` cast that nothing proves is
            // a number.
            $over = ReturnedQuantities::remaining($line->quantity, $left);

            if (bccomp($over, '0', self::SCALE) <= 0) {
                continue;
            }

            $exceeded[] = [
                // Null where the product has since been force-deleted — the same nothing every
                // other screen in this module shows for it.
                'name' => $source->product?->name,
                // Full scale, both of them. Trimming for display is the controller's job.
                'remaining' => $left,
                'requested' => $line->quantity,
            ];
        }

        if ($exceeded !== []) {
            throw new ReturnExceedsOrderException($exceeded);
        }
    }

    /**
     * One line's goods back onto the shelf.
     *
     * **One movement per line, not per product.** The ledger records what the document says, and
     * collapsing two lines of five into one row of ten would make it disagree with the credit
     * note somebody is holding.
     *
     * **An archived product still comes back**, exactly as an archived one is still despatched:
     * retiring something from the catalogue does not unpick a sale, and refusing here would leave
     * a customer credited for goods the shelf never regained. A hard-deleted one is the single
     * case skipped — there is no row left to hold a level against, and `record()` would be handed
     * `null` where it declares a `Model`.
     *
     * **Nothing is caught in here**, deliberately: `record()` opens its own transaction, which
     * Laravel turns into a savepoint, so catching and continuing would roll back the savepoint
     * while the outer transaction went on to commit a completed return with a movement missing.
     *
     * @throws InsufficientStockException
     */
    private function receiveLine(
        SalesReturn $return,
        SalesReturnItem $line,
        Warehouse $warehouse,
        ?User $user,
    ): void {
        $product = $line->salesOrderItem->product;

        if (! $product instanceof Product) {
            return;
        }

        // Positive, always: goods coming back only ever add. No `negate()` here, and that one
        // character is the whole difference between this Action and its opposite number's write
        // loop. The source is the *return* — the document that moved the stock — never the order
        // it credits, which is one hop from there.
        $this->stock->record(
            $warehouse,
            $product,
            (string) $line->quantity,
            StockMovementReason::SalesReturn,
            $user,
            $return->notes,
            $return,
        );
    }

    /**
     * The products whose level rows this transaction must hold.
     *
     * Deduplicated by key, because two lines of one product are one row to lock and
     * {@see StockService::lockLevels()} would otherwise take the same lock twice — harmless, but
     * it makes the canonical ordering harder to reason about than it needs to be.
     *
     * @param  Collection<int, SalesReturnItem>  $lines
     * @return list<Product>
     */
    private static function products(Collection $lines): array
    {
        $products = [];

        foreach ($lines as $line) {
            $product = $line->salesOrderItem->product;

            if ($product instanceof Product) {
                $products[$product->id] = $product;
            }
        }

        return array_values($products);
    }
}
