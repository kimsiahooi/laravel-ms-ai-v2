<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StockTakeStatus;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opens a draft count for one warehouse and prints its sheet.
 *
 * Two writes that have to be one, which is the whole reason this is an Action rather
 * than controller code: the take is created and then every line the warehouse currently
 * holds is snapshotted onto it. A take whose header committed and whose lines did not
 * would look like a warehouse holding nothing — and the person counting would take that
 * at face value and post an empty sheet over a full building.
 *
 * **The snapshot is a photograph, not a promise.** `system_quantity` records what the
 * system believed at the moment the sheet was printed, so the counter has something to
 * confirm or contradict. Nothing at posting time reads it — see {@see PostStockTake},
 * which asks {@see StockService} for the difference under the lock — and that separation
 * is deliberate: a sheet left open over a shift is *expected* to go stale, and a stale
 * number is only dangerous if arithmetic depends on it.
 *
 * **`counted_quantity` starts null, and null is the point.** v1 preseeded every line to
 * its system quantity, which erased the difference between a shelf nobody had reached
 * and one counted at exactly the expected number. Null is what "not counted yet" looks
 * like, and it is what lets a half-finished sheet be posted without touching the half
 * nobody walked.
 *
 * The sheet is a starting point rather than a closed list. An item found on a shelf the
 * warehouse has no stock row for is added afterwards through the controller — v1 could
 * only count what it already knew about, so a genuine surplus had nowhere to be written
 * down.
 */
final class OpenStockTake
{
    /**
     * How many stock rows are turned into lines per round trip.
     *
     * The snapshot is the one place in this module that touches every item in a
     * warehouse at once, so it is written in batches rather than a model at a time: a
     * ten-thousand-item warehouse would otherwise open with ten thousand inserts, inside
     * a transaction, while somebody watches a spinner. Five hundred keeps each statement
     * and each hydrated chunk small enough not to matter either way.
     */
    private const CHUNK = 500;

    public function handle(Warehouse $warehouse, ?User $user = null, ?string $notes = null): StockTake
    {
        return DB::transaction(function () use ($warehouse, $user, $notes): StockTake {
            // forceCreate for the reason the model gives: nothing about a stock take is
            // mass-assignable from a request, so every column is named right here.
            // `posted_by` and `posted_at` are named as nulls rather than left to the
            // schema, because the pair is what says this has not been posted.
            $take = StockTake::query()->forceCreate([
                'warehouse_id' => $warehouse->id,
                'status' => StockTakeStatus::Draft,
                'created_by' => $user?->id,
                'posted_by' => null,
                'posted_at' => null,
                'notes' => $notes,
            ]);

            $this->snapshot($take, $warehouse);

            return $take;
        });
    }

    /**
     * One line per live stock row in the warehouse.
     *
     * `whereHasMorph` rather than a join, and not only because controllers may not join:
     * the stockable is a morph over two catalogue tables, which no single join expresses,
     * and each model's own soft-delete scope is what makes "live" mean the same thing
     * here as everywhere else. An archived product is deliberately left off the sheet —
     * asking somebody to count something the catalogue has retired is asking a question
     * nobody can act on.
     *
     * A row holding zero still becomes a line. Zero is a claim the system is making, and
     * a claim is exactly what a count exists to check.
     */
    private function snapshot(StockTake $take, Warehouse $warehouse): void
    {
        $now = now();

        WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->whereHasMorph('stockable', [Product::class, RawMaterial::class])
            ->chunkById(self::CHUNK, function (Collection $stocks) use ($take, $now): void {
                // A single insert per chunk, which is also why the timestamps are set by
                // hand: the query builder writes rows, not models, so nothing is
                // listening to fill them in.
                StockTakeItem::query()->insert(
                    $stocks->map(static fn (WarehouseStock $stock): array => [
                        'stock_take_id' => $take->id,
                        'stockable_type' => $stock->stockable_type,
                        'stockable_id' => $stock->stockable_id,
                        'system_quantity' => (string) $stock->quantity,
                        'counted_quantity' => null,
                        'applied_delta' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->all(),
                );
            });
    }
}
