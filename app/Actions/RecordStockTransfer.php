<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Moves stock between two warehouses and records that somebody did so.
 *
 * Two writes that have to be one: {@see StockService::transfer()} appends the pair of
 * ledger rows and moves the on-hand, and this adds the document that says the pair
 * belong together. A transfer whose movements landed but whose document did not would
 * be indistinguishable from two unrelated adjustments.
 *
 * **An Action rather than a fourth method on StockService.** The service's invariant is
 * that `warehouse_stocks` and `stock_movements` never disagree, and `stock_transfers`
 * is no part of that — it is a record of intent, not of stock. Keeping it out also
 * leaves `transfer()` exactly as `stock:hammer --deadlock` proved it, which is the only
 * evidence the lock ordering works.
 *
 * The outer transaction is what makes the two atomic. `transfer()` opens its own, which
 * Laravel turns into a savepoint, so a failure anywhere — including the service's
 * refusal to go below zero — takes the document with it.
 */
final class RecordStockTransfer
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  string  $quantity  a positive magnitude; the direction is `from` → `to`.
     *                            Not narrowed to `numeric-string`: the value arrives from
     *                            a request, so no caller can prove it, and
     *                            {@see StockService} guards it under the lock where a
     *                            bad one can still be refused.
     */
    public function handle(
        Warehouse $from,
        Warehouse $to,
        Model $stockable,
        string $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): StockTransfer {
        return DB::transaction(function () use ($from, $to, $stockable, $quantity, $user, $notes): StockTransfer {
            // The document is written first now, because the movements point back at it.
            // Order inside the transaction is free — either both land or neither does —
            // and the alternative is writing the ledger rows and updating them after,
            // which is two writes where one will do.
            //
            // forceCreate for the reason the model gives: nothing about a transfer is
            // mass-assignable from a request, so every column is named right here.
            $transfer = StockTransfer::query()->forceCreate([
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'stockable_type' => $stockable->getMorphClass(),
                'stockable_id' => $stockable->getKey(),
                'quantity' => $quantity,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);

            // A short source leaves nothing behind: the refusal throws, and the document
            // above rolls back with it.
            $this->stock->transfer($from, $to, $stockable, $quantity, $user, $notes, $transfer);

            return $transfer;
        });
    }
}
