<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only thing that writes stock.
 *
 * Every on-hand change in the application goes through one of the three public methods
 * here. Each locks the (warehouse, item) row, applies a signed delta, refuses to go
 * below zero, and appends exactly one ledger row — all inside one transaction, which is
 * what stops `warehouse_stocks` and `stock_movements` from ever disagreeing. The one
 * exception is a {@see setLevel()} that changes nothing, which writes neither: no
 * change, no row, and the two tables still agree.
 *
 * **Quantities are decimal strings, not floats.** v1 used `float` throughout and it is
 * *nearly* safe: every value round-trips through a `decimal(15,4)` column, which scrubs
 * the representation error on each write, and two equal decimals parse to two equal
 * doubles, so the `< 0` check cannot misfire. What is not safe is accumulation. On-hand
 * is not bounded by the per-movement maximum, and once it passes what a double carries,
 * PHP's `precision=14` string conversion silently drops the tail:
 *
 *     (string) ((float) '99999999999' + (float) '0.0001')  ===  '99999999999'
 *
 * The ledger would record the 0.0001 and on-hand would not move — the two tables
 * drifting apart, which is the one thing this class exists to prevent. Unlikely, silent,
 * and free to remove: `bcadd`/`bccomp` at the column's own scale cannot do it, and the
 * rest of v2 already treats decimals as strings (see `BomItemData`).
 *
 * **Locks are taken in a fixed order** — see {@see transfer()}.
 */
final class StockService
{
    /** The scale of `decimal(15,4)`. Every bcmath call here uses it. */
    private const SCALE = 4;

    /**
     * Apply a signed delta to on-hand and append the ledger row that explains it.
     *
     * @param  string  $delta  positive in, negative out
     *
     * @throws InsufficientStockException when it would drive on-hand below zero
     */
    public function record(
        Warehouse $warehouse,
        Model $stockable,
        string $delta,
        StockMovementReason $reason,
        ?User $user = null,
        ?string $notes = null,
    ): StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $delta, $reason, $user, $notes): StockMovement {
            $this->applyLockedDelta($warehouse, $stockable, $delta);

            return $this->writeMovement($warehouse, $stockable, $delta, $reason, $user, $notes);
        });
    }

    /**
     * Move a quantity of one item between two warehouses.
     *
     * The out, the in and both ledger rows commit together or not at all, so a source
     * that turns out to be short rolls back the destination's gain rather than creating
     * stock from nothing.
     *
     * **Both rows are locked up front, ordered by warehouse id.** v1 locked the source
     * and then the destination, which means A→B and B→A running at the same time each
     * hold the lock the other needs — a deadlock, and one that only appears under
     * concurrency. Taking the locks in the same order every time makes that impossible;
     * `stock:hammer --deadlock` is the demonstration.
     *
     * @return array{StockMovement, StockMovement} the out, then the in
     *
     * @throws InsufficientStockException when the source lacks the quantity
     */
    public function transfer(
        Warehouse $from,
        Warehouse $to,
        Model $stockable,
        string $quantity,
        ?User $user = null,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use ($from, $to, $stockable, $quantity, $user, $notes): array {
            foreach ($this->orderedIds($from, $to) as $warehouseId) {
                $this->lockRow($warehouseId, $stockable);
            }

            return [
                $this->record($from, $stockable, $this->negate($quantity), StockMovementReason::TransferOut, $user, $notes),
                $this->record($to, $stockable, $quantity, StockMovementReason::TransferIn, $user, $notes),
            ];
        });
    }

    /**
     * Set on-hand to a counted total, recording the difference as one movement.
     *
     * The read and the write happen under the same lock, so two people counting the
     * same shelf cannot both compute their delta from the same starting number.
     *
     * **A count that matches writes nothing, and null says so.** The ledger records what
     * moved; a confirmed level did not move, and appending `0.0000` would put a row in
     * the history that says nothing happened. It is not a rounding concern but a
     * readability one, and at the scale a stock take works at it is a volume one too — a
     * 500-line count of a well-run warehouse would file 500 empty rows and bury the
     * handful that mattered. The confirmation is not lost: the stock take's own line
     * records that the shelf was counted and agreed, which is where that belongs.
     *
     * @return StockMovement|null null when the target already equalled on-hand
     *
     * @throws InsufficientStockException when the target is negative
     */
    public function setLevel(
        Warehouse $warehouse,
        Model $stockable,
        string $target,
        StockMovementReason $reason = StockMovementReason::StockTake,
        ?User $user = null,
        ?string $notes = null,
    ): ?StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $target, $reason, $user, $notes): ?StockMovement {
            $stock = $this->lockRow($warehouse->id, $stockable);
            $current = $stock === null ? $this->zero() : $this->decimal((string) $stock->quantity);
            $delta = bcsub($this->decimal($target), $current, self::SCALE);

            // Before applyLockedDelta, not after: a zero delta has nothing to apply, and
            // returning here leaves `warehouse_stocks` untouched rather than rewriting a
            // row with the value it already holds.
            if (bccomp($delta, '0', self::SCALE) === 0) {
                return null;
            }

            $this->applyLockedDelta($warehouse, $stockable, $delta);

            return $this->writeMovement($warehouse, $stockable, $delta, $reason, $user, $notes);
        });
    }

    /**
     * What is on hand right now, for display only.
     *
     * Unlocked, so the answer is stale the moment it is returned. Anything that acts on
     * it must go through the methods above, which re-read under a lock — a screen
     * showing 5 and a person issuing 5 is exactly the race those exist for.
     *
     * @return numeric-string
     */
    public function onHand(Warehouse $warehouse, Model $stockable): string
    {
        $quantity = WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->value('quantity');

        return $quantity === null ? $this->zero() : $this->decimal((string) $quantity);
    }

    /**
     * Lock the on-hand row, add the delta, refuse a negative result, and persist.
     *
     * Must run inside a transaction; every caller here opens one.
     *
     * @throws InsufficientStockException
     */
    private function applyLockedDelta(Warehouse $warehouse, Model $stockable, string $delta): void
    {
        $stock = $this->lockRow($warehouse->id, $stockable);

        $current = $stock === null ? $this->zero() : $this->decimal((string) $stock->quantity);
        $next = bcadd($current, $this->decimal($delta), self::SCALE);

        if (bccomp($next, '0', self::SCALE) < 0) {
            throw new InsufficientStockException(
                available: $current,
                // The shortfall is reported as what was asked for, unsigned: the caller
                // asked to take 7, not to add -7.
                requested: ltrim($delta, '-'),
                message: 'Movement would drive on-hand below zero.',
            );
        }

        if ($stock !== null) {
            $stock->forceFill(['quantity' => $next])->save();

            return;
        }

        // forceCreate, and both models are left entirely unfillable on purpose. These
        // two tables are never written from a request — this class is the only writer,
        // and it names every column right here. Mass-assignment protection guards
        // against a request array reaching a model; there is no request array in this
        // file, so declaring a fillable list would only make the tables writable by
        // something that should not be writing them.
        WarehouseStock::query()->forceCreate([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'quantity' => $next,
        ]);
    }

    /**
     * The on-hand row under `SELECT … FOR UPDATE`, or null when the item has never
     * moved through this warehouse.
     *
     * A null is not an unlocked call. Under MySQL's REPEATABLE READ the compound unique
     * index gives InnoDB a gap to lock, so a second transaction asking the same question
     * blocks here rather than racing to insert the same row — which is why that index is
     * described in the migration as load-bearing.
     */
    private function lockRow(int $warehouseId, Model $stockable): ?WarehouseStock
    {
        return WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('stockable_type', $stockable->getMorphClass())
            ->where('stockable_id', $stockable->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     * The same quantity, the other way round.
     *
     * `'-'.$quantity` would produce `'--5'` for a quantity that already carried a sign,
     * which bcmath rejects. Subtracting from zero cannot.
     *
     * @return numeric-string
     */
    private function negate(string $quantity): string
    {
        return bcsub('0', $this->decimal($quantity), self::SCALE);
    }

    /**
     * The two warehouse ids, smallest first. See {@see transfer()}.
     *
     * @return list<int>
     */
    private function orderedIds(Warehouse $from, Warehouse $to): array
    {
        $ids = [$from->id, $to->id];
        sort($ids);

        return array_values(array_unique($ids));
    }

    /** Append one ledger row. */
    private function writeMovement(
        Warehouse $warehouse,
        Model $stockable,
        string $delta,
        StockMovementReason $reason,
        ?User $user,
        ?string $notes,
    ): StockMovement {
        return StockMovement::query()->forceCreate([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'quantity' => $delta,
            'reason' => $reason,
            'user_id' => $user?->id,
            'notes' => $notes,
        ]);
    }

    /**
     * A quantity, proven to be a number.
     *
     * bcmath in PHP 8 throws a `ValueError` on a non-numeric string, which surfaces as a
     * 500 naming a bcmath argument rather than the value somebody actually sent. This
     * refuses it at the door with the value in the message — and it is what lets the
     * static analyser see that every operand below really is numeric, rather than being
     * told to assume it.
     *
     * @return numeric-string
     */
    private function decimal(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                sprintf('Stock quantities must be numeric, got "%s".', $value),
            );
        }

        return $value;
    }

    /**
     * `'0.0000'` — zero at the column's scale, so every string here has one shape.
     *
     * @return numeric-string
     */
    private function zero(): string
    {
        return bcadd('0', '0', self::SCALE);
    }
}
