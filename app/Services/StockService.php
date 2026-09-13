<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\FulfillSalesOrder;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Support\StockItem;
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
     * @param  Model|null  $source  the document that caused this — a stock take, a
     *                              transfer, later an order. Recorded as a relationship
     *                              rather than spelled into `$notes`, which is where v1
     *                              put it six times over: a reference built by string
     *                              concatenation can only be one language and has to be
     *                              parsed to be read back. `$notes` stays what a person
     *                              typed.
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
        ?Model $source = null,
    ): StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $delta, $reason, $user, $notes, $source): StockMovement {
            $this->applyLockedDelta($warehouse, $stockable, $delta);

            return $this->writeMovement($warehouse, $stockable, $delta, $reason, $user, $notes, $source);
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
        ?Model $source = null,
    ): array {
        return DB::transaction(function () use ($from, $to, $stockable, $quantity, $user, $notes, $source): array {
            foreach ($this->orderedIds($from, $to) as $warehouseId) {
                $this->lockRow($warehouseId, $stockable);
            }

            // Both legs carry the same source, which is what finally joins them: two rows
            // reading `transfer_out` and `transfer_in` never said they belonged together.
            return [
                $this->record($from, $stockable, $this->negate($quantity), StockMovementReason::TransferOut, $user, $notes, $source),
                $this->record($to, $stockable, $quantity, StockMovementReason::TransferIn, $user, $notes, $source),
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
        ?Model $source = null,
    ): ?StockMovement {
        return DB::transaction(function () use ($warehouse, $stockable, $target, $reason, $user, $notes, $source): ?StockMovement {
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

            return $this->writeMovement($warehouse, $stockable, $delta, $reason, $user, $notes, $source);
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
     * What is on hand for several items in one warehouse, for display only.
     *
     * The plural of {@see onHand()} and stale in exactly the same way, so everything that
     * class says applies here: it takes no lock, nothing may act on the answer, and a screen
     * showing a number is never the thing that decides. One query rather than one per item,
     * because the caller is a document with as many lines as a delivery has.
     *
     * Keyed by {@see StockItem::key()} — `product:5` — which is the string the pickers, the
     * ledger and the order lines already agree on, so a caller holding a model can look its
     * own row up without knowing how the morph columns are spelled. Items this warehouse has
     * never held are absent; a caller reads a missing key as zero, and {@see zero()} is the
     * shape to read it as.
     *
     * @param  list<Model>  $stockables
     * @return array<string, numeric-string>
     */
    public function onHandFor(Warehouse $warehouse, array $stockables): array
    {
        if ($stockables === []) {
            return [];
        }

        $levels = [];

        // Grouped by morph class, because `stockable_id` alone is ambiguous across the two
        // catalogue tables: product 5 and raw material 5 are different things with the same
        // id, and one `whereIn` over both would read either's level as the other's.
        foreach ($this->byMorphClass($stockables) as $type => $ids) {
            $rows = WarehouseStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('stockable_type', $type)
                ->whereIn('stockable_id', $ids)
                ->pluck('quantity', 'stockable_id');

            foreach ($rows as $id => $quantity) {
                $levels[StockItem::key($type, (int) $id)] = $this->decimal((string) $quantity);
            }
        }

        return $levels;
    }

    /**
     * Lock this warehouse's level row for every item at once, and hand back what each holds.
     *
     * For a caller about to write several movements in one transaction — issuing a sales
     * order, today. It exists so that all the locks are taken **before** anything is decided,
     * which is what makes an all-or-nothing check honest: a shortfall found on line nine
     * cannot be raced by somebody else taking line one's stock in between.
     *
     * **The locks are taken in one canonical order**, by morph class and then by id, for
     * exactly the reason {@see transfer()} orders its two by warehouse id: two despatches out
     * of the same building that overlap on two products would otherwise each hold the row the
     * other needs. `ReceivePurchaseOrder` locks one row per line in line order and is the
     * latent version of that deadlock; it should adopt this.
     *
     * **The levels come back from the locking read itself, and that is not a convenience.**
     * Under MySQL's REPEATABLE READ a plain `SELECT` after this would answer from the
     * transaction's snapshot — established by whatever read came first, typically the order's
     * own lines — while the locking read sees the latest committed row. A caller that locked
     * here and then asked {@see onHandFor()} could be told a number that a committed
     * transaction had already moved, which is the one thing the lock was taken to prevent.
     *
     * Must run inside a transaction; a lock released at the end of its own statement is not
     * a lock.
     *
     * @param  list<Model>  $stockables
     * @return array<string, numeric-string> keyed by {@see StockItem::key()}, as
     *                                       {@see onHandFor()} keys it — items this
     *                                       warehouse has never held are absent
     */
    public function lockLevels(Warehouse $warehouse, array $stockables): array
    {
        $levels = [];

        foreach ($this->byMorphClass($stockables) as $type => $ids) {
            sort($ids);

            foreach ($ids as $id) {
                $stock = WarehouseStock::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('stockable_type', $type)
                    ->where('stockable_id', $id)
                    ->lockForUpdate()
                    ->first();

                // Absent rather than zero, so the shape matches onHandFor() and a caller has
                // one rule to read both by. The row is still gap-locked — see lockRow().
                if ($stock !== null) {
                    $levels[StockItem::key($type, $id)] = $this->decimal((string) $stock->quantity);
                }
            }
        }

        return $levels;
    }

    /**
     * The ids to look up, grouped by the morph key they are stored under and ordered by
     * class name, so two callers asking about the same set walk it the same way.
     *
     * @param  list<Model>  $stockables
     * @return array<string, list<int>>
     */
    private function byMorphClass(array $stockables): array
    {
        $grouped = [];

        foreach ($stockables as $stockable) {
            $grouped[$stockable->getMorphClass()][] = (int) $stockable->getKey();
        }

        foreach ($grouped as $type => $ids) {
            $grouped[$type] = array_values(array_unique($ids));
        }

        ksort($grouped);

        return $grouped;
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
     * Public because {@see FulfillSalesOrder} issues goods and has to hand
     * `record()` a negative delta. An Action reinventing this is exactly how the `'--5'`
     * gets written for the first time, and the failure would be a `ValueError` naming a
     * bcmath argument rather than the order it came from.
     *
     * @return numeric-string
     */
    public function negate(string $quantity): string
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
        ?Model $source = null,
    ): StockMovement {
        return StockMovement::query()->forceCreate([
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $stockable->getMorphClass(),
            'stockable_id' => $stockable->getKey(),
            'quantity' => $delta,
            'reason' => $reason,
            'user_id' => $user?->id,
            'notes' => $notes,
            // Through the morph map, so the column holds `stock_take` rather than a
            // class name that a namespace change would strand — see AppServiceProvider.
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
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
