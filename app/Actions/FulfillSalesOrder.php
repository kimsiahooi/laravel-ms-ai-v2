<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InsufficientStockForOrderException;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\OrderAvailability;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ships a sales order out of one warehouse, as one despatch.
 *
 * The mirror of {@see ReceivePurchaseOrder}, and the one place in the module where the two
 * sides stop mirroring: a receipt only ever adds, so it cannot fail on quantity, while a
 * despatch is a subtraction and the shelf is allowed to say no. Everything below that is not
 * a transposition exists because of that one difference.
 *
 * **All or nothing.** A shortfall on any line ships nothing, leaves the order pending, and
 * names what was short. There is no `fulfilled_quantity` column anywhere, so a partial is not
 * a state this document can express — and {@see ReceivePurchaseOrder} already argues the
 * mirror case: *"A half-received order is worse than an unreceived one."* A half-issued order
 * says the goods left while the shelf still holds some of them.
 *
 * **The check runs over the whole order before a single movement is written**, which is what
 * makes that promise cheap rather than a rollback. Relying on `record()` to refuse the
 * eleventh line would work — the transaction would unwind — but the only thing it could
 * report is that line, so somebody would find the shortfalls one press at a time.
 *
 * **Quantities are added up per product, not checked per line.** There is no unique index on
 * (order, product) — see the migration — so two lines of five against eight on the shelf must
 * be refused, and a per-line reading passes both of them. {@see OrderAvailability} is where
 * that addition lives, and the screen's panel goes through the same function, so a green row
 * and a refusal cannot disagree.
 *
 * **Every level row is locked up front, in one canonical order.** {@see StockService::lockLevels()}
 * says why: two despatches out of the same building that overlap on two products would
 * otherwise each hold the row the other needs. Holding them all from before the check until
 * the commit is also what makes the check a guarantee rather than an opinion — nothing can
 * move between deciding and writing.
 *
 * **The order is re-read under `FOR UPDATE`**, for the reason `ReceivePurchaseOrder` spells
 * out at length: v1 checked the status on the model the route had already bound, outside any
 * transaction, so two people pressing Fulfil at the same moment both saw a pending order and
 * both issued the goods.
 *
 * **The race that is left is between the *screen* and this method, and it is meant to be
 * there.** The availability panel reads levels without a lock, so its numbers are stale the
 * moment they arrive — which is why the button it sits above is never disabled by them. This
 * is where the question is actually settled.
 */
final class FulfillSalesOrder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws InsufficientStockForOrderException when one or more products cannot be covered
     *                                            by this warehouse. Nothing is written.
     * @throws InsufficientStockException declared because {@see StockService::record()}
     *                                    declares it, and unreachable: every level row is
     *                                    held under `FOR UPDATE` from before the check, so
     *                                    step four re-locks rows this transaction already
     *                                    owns. The controller still catches it — a lock path
     *                                    reasoned about and not proven deserves a last line
     *                                    of defence that is not a 500.
     * @throws DomainException when the order stopped being pending between the controller's
     *                         check and this lock — the true double-press race.
     */
    public function handle(SalesOrder $order, Warehouse $warehouse, ?User $user = null): SalesOrder
    {
        return DB::transaction(function () use ($order, $warehouse, $user): SalesOrder {
            $locked = SalesOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            // Already fulfilled, already cancelled, or deleted from under us. The ordinary
            // press against a non-pending order never reaches here — the controller refuses
            // it first, with a sentence a person can read. Arriving here means the status
            // changed *after* that check.
            if ($locked === null || $locked->status !== SalesOrderStatus::Pending) {
                throw new DomainException('Sales order is no longer pending.');
            }

            // `product` is eager-loaded withTrashed by the relation itself, so a line whose
            // product was archived after the order was taken still resolves.
            $lines = $locked->items()->with('product')->get();

            $required = OrderAvailability::required($lines);

            // Every lock first, then the whole check, then the writes. The levels come back
            // from the locking read rather than a plain one afterwards — see `lockLevels()`
            // on why REPEATABLE READ makes that distinction load-bearing.
            $levels = $this->stock->lockLevels($warehouse, OrderAvailability::products($required));

            $shortfalls = OrderAvailability::shortfalls(OrderAvailability::rows($required, $levels));

            if ($shortfalls !== []) {
                throw new InsufficientStockForOrderException($shortfalls);
            }

            foreach ($lines as $line) {
                $this->issueLine($locked, $line, $warehouse, $user);
            }

            $locked->forceFill([
                'status' => SalesOrderStatus::Fulfilled,
                'fulfilled_at' => now(),
                // Not the creator: the two are separate columns precisely so that both are
                // knowable — the person who takes an order over the phone is routinely not
                // the person who picks it. See the migration.
                'fulfilled_by' => $user?->id,
                'fulfilled_warehouse_id' => $warehouse->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * One line's goods out of the warehouse.
     *
     * **One movement per line, not per product**, even though the check adds the lines of a
     * product together. The ledger records what the document says: an order with two lines of
     * five leaves two rows of five, each traceable to the row it came from, and collapsing
     * them into one row of ten would make the ledger disagree with the despatch note somebody
     * is holding.
     *
     * **An archived product still ships**, exactly as an archived material is still received:
     * somebody retiring a product from the catalogue does not unpick a sale, and refusing here
     * would leave goods on the shelf that the customer has been invoiced for. A hard-deleted
     * one (`product_id` null) is the one case skipped — there is no row left to hold a level
     * against — and it costs the order nothing, because the line still records what was
     * charged.
     *
     * **Nothing is caught in here**, and that is deliberate: `record()` opens its own
     * transaction, which Laravel turns into a savepoint, so catching and continuing would roll
     * back the savepoint while the outer transaction went on to commit a fulfilled order with
     * a movement missing from it.
     *
     * @throws InsufficientStockException
     */
    private function issueLine(
        SalesOrder $order,
        SalesOrderItem $line,
        Warehouse $warehouse,
        ?User $user,
    ): void {
        $product = $line->product;

        if (! $product instanceof Product) {
            return;
        }

        // Negative, always: a despatch only ever takes. `negate()` rather than `'-'.$quantity`,
        // which would produce `'--5'` the day a quantity arrives already signed and fail as a
        // bcmath ValueError. `notes` carries what a person typed on the order, exactly as a
        // receipt passes its own along, and the order itself is handed over as the source —
        // never a reference spelled into the note.
        $this->stock->record(
            $warehouse,
            $product,
            $this->stock->negate((string) $line->quantity),
            StockMovementReason::SalesFulfillment,
            $user,
            $order->notes,
            $order,
        );
    }
}
