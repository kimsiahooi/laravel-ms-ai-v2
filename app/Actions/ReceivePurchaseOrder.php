<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Books a purchase order's goods into a warehouse, as one delivery.
 *
 * One movement per line, all positive, all inside one transaction with the status change
 * that says the order has been received. {@see StockService::record()} opens its own,
 * which Laravel turns into a savepoint, so a refusal on the last line takes the lines
 * before it with it. A half-received order is worse than an unreceived one: the document
 * would say the goods arrived and the warehouse would hold some of them.
 *
 * **The order is re-read under `FOR UPDATE` inside the transaction, and that is the whole
 * reason this is not four lines in a controller.** v1 checked the status on the model the
 * route had already bound, outside any transaction, and then aborted with a 422 — so two
 * people pressing Receive at the same moment both saw a pending order and both received
 * it, and the warehouse gained the delivery twice. The second press now blocks on this
 * lock and finds the status the first one wrote. It is told so rather than being handed
 * the order back quietly: returning would let it report "received and stock updated" for
 * work it did not do.
 *
 * **The order is the movement's `source`, and the note is what a person typed.** v1 passed
 * the string `"PO #{$order->id}"` as the movement's note — a reference built by
 * concatenation, which can only ever be one language, has to be parsed to be read back,
 * and leaves `notes` meaning two things at once so neither can be trusted. The `source`
 * columns on `stock_movements` exist for exactly this, and every ledger row a receipt
 * writes now points at the document that caused it.
 */
final class ReceivePurchaseOrder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws InsufficientStockException declared because {@see StockService::record()}
     *                                    declares it, and unreachable here: a receipt is
     *                                    in-only, every quantity is validated `gt:0`, and
     *                                    adding to a level cannot drive it below zero.
     *                                    The controller still catches it, because the day
     *                                    somebody adds a partial receipt that nets out is
     *                                    not the day to discover this was a 500.
     * @throws DomainException when the order stopped being pending between the
     *                         controller's check and this lock — the true
     *                         double-press race.
     */
    public function handle(PurchaseOrder $order, Warehouse $warehouse, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $warehouse, $user): PurchaseOrder {
            $locked = PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            // Already received, already cancelled, or deleted from under us. The ordinary
            // press against a non-pending order never reaches here — the controller
            // refuses it first, with a sentence a person can read. Arriving here means
            // the status changed *after* that check.
            if ($locked === null || $locked->status !== PurchaseOrderStatus::Pending) {
                throw new DomainException('Purchase order is no longer pending.');
            }

            // `rawMaterial` is eager-loaded withTrashed by the relation itself, so a line
            // whose material was archived after the order was raised still resolves.
            $lines = $locked->items()->with('rawMaterial')->get();

            foreach ($lines as $line) {
                $this->receiveLine($locked, $line, $warehouse, $user);
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Received,
                'received_at' => now(),
                // Not the creator: the two are separate columns precisely so that both
                // are knowable — see the migration.
                'received_by' => $user?->id,
                'received_warehouse_id' => $warehouse->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * One line's goods into the warehouse.
     *
     * **An archived material is still received.** Somebody retiring a material from the
     * catalogue does not stop a lorry that is already on its way, and skipping the line
     * would put stock in the building that the ledger does not know about — which is a
     * worse state than an archived material holding a level nobody is offering to order
     * again. This is the opposite call from {@see PostStockTake}, deliberately: a count of
     * something no longer in the catalogue is a claim about a shelf, and can be dropped;
     * a delivery is goods that physically arrived, and cannot.
     *
     * A hard-deleted material is the one case that is skipped, because `raw_material_id`
     * is then null and there is no row left to hold a level against. It costs the order
     * nothing — the line still records what was paid.
     *
     * @throws InsufficientStockException
     */
    private function receiveLine(
        PurchaseOrder $order,
        PurchaseOrderItem $line,
        Warehouse $warehouse,
        ?User $user,
    ): void {
        $material = $line->rawMaterial;

        // Named rather than tested as a bare Model: `record()` takes any Model, and the
        // null case here is a real outcome — see the method note — not an analyser
        // formality.
        if (! $material instanceof RawMaterial) {
            return;
        }

        // Positive, always: a receipt only ever adds. `notes` carries what a person typed
        // on the order, exactly as a transfer passes its own along, and the order itself
        // is handed over as the source — never a reference spelled into the note.
        $this->stock->record(
            $warehouse,
            $material,
            (string) $line->quantity,
            StockMovementReason::PurchaseReceipt,
            $user,
            $order->notes,
            $order,
        );
    }
}
