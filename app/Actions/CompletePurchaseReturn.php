<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InsufficientStockForOrderException;
use App\Exceptions\ReturnExceedsOrderException;
use App\Http\Requests\Tenant\PurchaseReturnRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\OrderAvailability;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends the goods back: one despatch out of one warehouse, against one delivery.
 *
 * The mirror of {@see FulfillSalesOrder} — both take stock off a shelf, both are all-or-nothing,
 * both refuse before they write anything — with one addition that no other document in this app
 * has. A return can be refused for **two independent reasons**: the warehouse may not hold the
 * goods, and the delivery may no longer have room for them because a sibling return completed
 * first. The second is the whole reason this Action is more than a transposition.
 *
 * **The two ceilings are different questions, and this is the narrow one.**
 * {@see PurchaseReturnRequest} refuses an over-return at save time counting *pending* siblings
 * too, because a second claim on goods another claim already covers is not a sensible document to
 * raise. Here the question is only "can this be done right now", so a pending sibling counts for
 * nothing — it has taken nothing off a shelf. See
 * {@see ReturnedQuantities::completedForOrderItems()}.
 *
 * ## The lock order, and why every step of it is where it is
 *
 *     purchase_returns row  →  purchase_orders row  →  [ceiling read]  →  warehouse_stocks rows
 *
 * **The return's own row** is the double-press guard, for the reason `ReceivePurchaseOrder`
 * spells out at length: v1 read the status off the model the route had already bound, outside
 * any transaction, so two people pressing at the same moment both saw a pending document.
 *
 * **The parent order's row is the rendezvous, and it is load-bearing for a subtler reason than
 * it looks.** The obvious one is that two returns against one delivery are two different rows, so
 * locking each return alone serialises nothing between them; they share an order, and that row is
 * the one object both must pass through. The real one is *visibility*. The ceiling's
 * `status = completed` test lives inside a `whereHas`, which compiles to a correlated `EXISTS`,
 * and MySQL is explicit that a locking read in an outer statement does not lock the rows of a
 * table in a nested subquery — so that predicate is answered by a consistent read whatever the
 * outer statement asks for. Under REPEATABLE READ a consistent read answers from the
 * transaction's read view, and the only way to make that read view late enough is to hold, before
 * it is created, a lock every competing completion must also have held.
 *
 * **So the invariant is: nothing in this transaction reads without a lock until the order row is
 * held.** Steps one and two are locking reads, which take the latest committed row and do not
 * establish a read view; the first plain read comes after both. Moving a `->get()` above them for
 * readability would break this silently, which is why it is written down rather than implied.
 *
 * **The ceiling read itself is deliberately *not* `FOR UPDATE`.** It looks like the obvious
 * hardening and it is the wrong call twice over. It would protect against nothing reachable: the
 * only thing that puts a line into the `Completed` set is a return being completed, and that
 * requires the order lock this transaction is holding. And it would cost a deadlock — InnoDB
 * auto-created an index on `purchase_return_items.purchase_order_item_id` to serve that foreign
 * key, so a locking `whereIn` on it next-key-locks rows that {@see SavePurchaseReturn::revise()}
 * locks through the *other* index (`purchase_return_id`). Two indexes, two acquisition orders,
 * and `DB::transaction()` defaults to a single attempt, so it would surface as a 500 rather than
 * a retry.
 *
 * **Every level row is locked up front in one canonical order** —
 * {@see StockService::lockLevels()} says why — and held from before the check until the commit,
 * which is what makes the check a guarantee rather than an opinion.
 *
 * One side effect worth knowing about: raising a return takes a foreign-key *shared* lock on the
 * same `purchase_orders` row this method takes exclusively, and {@see DocumentNumberGenerator}
 * has already taken the sequence row by then. So a slow completion briefly queues new returns
 * against that delivery, and the sequence with them. Head-of-line blocking, not a deadlock, and
 * at a workspace's concurrency it is not worth a finer-grained lock.
 */
final class CompletePurchaseReturn
{
    /** The scale of `decimal(15,4)` — the quantity columns', and {@see ReturnedQuantities}'s. */
    private const SCALE = 4;

    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws ReturnExceedsOrderException when a sibling return completed first and the delivery
     *                                     no longer has room for this one. Nothing is written,
     *                                     and the return is left pending and still editable.
     * @throws InsufficientStockForOrderException when one or more materials cannot be covered by
     *                                            this warehouse. Nothing is written.
     * @throws InsufficientStockException declared because {@see StockService::record()} declares
     *                                    it, and unreachable: every level row is held under
     *                                    `FOR UPDATE` from before the check, so the write
     *                                    re-locks rows this transaction already owns. The
     *                                    controller still catches it — a lock path reasoned about
     *                                    and not proven deserves a last line of defence that is
     *                                    not a 500.
     * @throws DomainException when the return stopped being pending between the controller's
     *                         check and this lock — the true double-press race.
     */
    public function handle(PurchaseReturn $return, Warehouse $warehouse, ?User $user = null): PurchaseReturn
    {
        return DB::transaction(function () use ($return, $warehouse, $user): PurchaseReturn {
            $locked = PurchaseReturn::query()->whereKey($return->getKey())->lockForUpdate()->first();

            // Already completed, already cancelled, or deleted from under us — this model soft
            // deletes, so `null` is a real answer rather than an impossible one. The ordinary
            // press against a non-pending return never reaches here; the controller refuses it
            // first with a sentence a person can read. Arriving here means the status changed
            // *after* that check.
            if ($locked === null || $locked->status !== ReturnStatus::Pending) {
                throw new DomainException('Purchase return is no longer pending.');
            }

            // The rendezvous. `withTrashed()` because the relation this mirrors declares it and
            // the intent belongs on the page — though a received order cannot in fact be trashed,
            // since `PurchaseOrderController::destroy()` refuses anything but a pending one.
            //
            // Nothing re-checks that the order is still `Received`, and nothing needs to: there
            // is no route that un-receives one. It is locked to be held, not to be read.
            PurchaseOrder::query()
                ->withTrashed()
                ->whereKey($locked->purchase_order_id)
                ->lockForUpdate()
                ->first();

            // The first plain read of the transaction, and therefore where its read view begins
            // — after both locks, which is the whole argument above. Two hops to the material,
            // where a sales order's item is one, so the eager load is not optional: without it
            // this is two queries per line and then a third inside the write loop.
            $lines = $locked->items()->with('purchaseOrderItem.rawMaterial')->get();

            $this->refuseOverReturn($lines);

            // The closure is the only part of this a purchase return owns — which relation holds
            // the item. Added up *per material*, because nothing stops two order lines naming the
            // same one and a per-line reading would pass two fives against eight on the shelf.
            $required = OrderAvailability::demandsFrom(
                $lines,
                static fn (PurchaseReturnItem $line): ?RawMaterial => $line->purchaseOrderItem->rawMaterial,
            );

            // Every lock first, then the whole check, then the writes. The levels come back from
            // the locking read rather than a plain one afterwards — see `lockLevels()` on why
            // REPEATABLE READ makes that distinction load-bearing.
            $levels = $this->stock->lockLevels($warehouse, OrderAvailability::items($required));

            $shortfalls = OrderAvailability::shortfalls(OrderAvailability::rows($required, $levels));

            if ($shortfalls !== []) {
                throw new InsufficientStockForOrderException($shortfalls);
            }

            foreach ($lines as $line) {
                $this->issueLine($locked, $line, $warehouse, $user);
            }

            $locked->forceFill([
                'status' => ReturnStatus::Completed,
                'completed_at' => now(),
                // Not the creator: the person who raises a return is routinely not the person who
                // puts the goods on the courier. Separate columns so both stay knowable — the
                // shape `purchase_orders` set with `received_by`.
                'completed_by' => $user?->id,
                'completed_warehouse_id' => $warehouse->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * Refuse the whole document if any line no longer fits the delivery.
     *
     * **Compared line by line, where the shelf check adds up.** That asymmetry looks like a bug
     * and is not: `purchase_return_items_line_unique` guarantees one row per (return, order
     * line), so a line is the whole of this return's claim on that order line and there is
     * nothing to add together. The shelf has no such uniqueness — two order lines may name one
     * material — which is why {@see OrderAvailability} aggregates and this does not.
     *
     * **Every line that no longer fits, not the first**, for the reason
     * {@see InsufficientStockForOrderException} argues for itself: one press should reveal the
     * whole answer rather than the next thing wrong with it.
     *
     * @param  Collection<int, PurchaseReturnItem>  $lines
     *
     * @throws ReturnExceedsOrderException
     */
    private function refuseOverReturn(Collection $lines): void
    {
        $ids = array_values(array_unique(
            $lines->map(static fn (PurchaseReturnItem $line): int => $line->purchase_order_item_id)->all(),
        ));

        $completed = ReturnedQuantities::completedForOrderItems($ids);

        $exceeded = [];

        foreach ($lines as $line) {
            $source = $line->purchaseOrderItem;

            $left = ReturnedQuantities::remaining(
                $source->quantity,
                $completed[$line->purchase_order_item_id] ?? '0',
            );

            // `remaining()` read the other way round: how much of this line has nowhere to go. It
            // guards both operands and floors at zero, so a line that fits answers exactly zero
            // and a line that does not answers by how much — which avoids a third copy of the
            // numeric guard and a bare `bccomp` on a `decimal:4` cast that nothing proves is a
            // number.
            $over = ReturnedQuantities::remaining($line->quantity, $left);

            if (bccomp($over, '0', self::SCALE) <= 0) {
                continue;
            }

            $exceeded[] = [
                // Null where the material has since been force-deleted — the same nothing every
                // other screen in this module shows for it.
                'name' => $source->rawMaterial?->name,
                // Full scale, both of them. Trimming for display is the controller's job, the
                // rule `StockService` states: the arithmetic keeps every place it has.
                'remaining' => $left,
                'requested' => $line->quantity,
            ];
        }

        if ($exceeded !== []) {
            throw new ReturnExceedsOrderException($exceeded);
        }
    }

    /**
     * One line's goods out of the warehouse, and back to the supplier.
     *
     * **One movement per line, not per material**, even though the check adds the lines of a
     * material together — the ledger records what the document says, and collapsing two lines of
     * five into one row of ten would make it disagree with the return somebody is holding. The
     * same choice {@see FulfillSalesOrder::issueLine()} makes, for the same reason.
     *
     * **An archived material still goes back**, exactly as an archived one is still received:
     * retiring something from the catalogue does not unpick a delivery. A hard-deleted one is the
     * single case skipped — there is no row left to hold a level against, and `record()` would be
     * handed `null` where it declares a `Model`. It costs the return nothing: the line still
     * records what was credited.
     *
     * **Nothing is caught in here**, deliberately: `record()` opens its own transaction, which
     * Laravel turns into a savepoint, so catching and continuing would roll back the savepoint
     * while the outer transaction went on to commit a completed return with a movement missing
     * from it.
     *
     * @throws InsufficientStockException
     */
    private function issueLine(
        PurchaseReturn $return,
        PurchaseReturnItem $line,
        Warehouse $warehouse,
        ?User $user,
    ): void {
        $material = $line->purchaseOrderItem->rawMaterial;

        if (! $material instanceof RawMaterial) {
            return;
        }

        // Negative, always: goods going back only ever leave. `negate()` rather than
        // `'-'.$quantity`, which would produce `'--5'` the day a quantity arrives already signed
        // and fail as a bcmath ValueError. The source is the *return* — the document that moved
        // the stock — never the order it credits, which is one hop from there.
        $this->stock->record(
            $warehouse,
            $material,
            $this->stock->negate((string) $line->quantity),
            StockMovementReason::PurchaseReturn,
            $user,
            $return->notes,
            $return,
        );
    }
}
