<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\PostStockTake;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of a count sheet — see the migration for why it carries three quantities and
 * what each of them is allowed to be used for.
 *
 * No soft delete: a line only exists inside a sheet, and the sheet's own soft delete
 * already covers changing your mind about the whole count. There is nothing to remove a
 * line for either — a line left uncounted is skipped at posting, so an item added by
 * mistake costs nothing but a row.
 *
 * No `#[Fillable]` and `$guarded` left at its default, for the reason {@see StockMovement}
 * gives: `counted_quantity` arrives from a request but every other column decides what
 * moves. The controller writes the one field by name and {@see PostStockTake} writes
 * `applied_delta` by name, so a fillable list here could only widen what a request can
 * reach.
 *
 * @property int $id
 * @property int $stock_take_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $system_quantity what the system believed when the line was added
 * @property string|null $counted_quantity null means not counted yet, not counted zero
 * @property string|null $applied_delta signed, written by posting under the row lock
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StockTake $stockTake
 * @property-read Product|RawMaterial|null $stockable
 */
class StockTakeItem extends Model
{
    /** @return BelongsTo<StockTake, $this> */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /**
     * What was counted — a product or a raw material.
     *
     * `withTrashed`, for the reason the ledger gives: a posted sheet is a record and
     * outlives the catalogue, and a line that could no longer say what it counted would
     * be a puzzle rather than a record. It also matters before posting — an item removed
     * from the catalogue mid-count still has to render, so posting can skip it knowingly
     * rather than the sheet quietly losing a row. A force-delete does take the row, which
     * is why this stays nullable.
     *
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * All three read back as strings, never floats.
     *
     * See {@see StockService} on why the whole engine works in decimal strings;
     * `decimal:4` is what keeps that true on the way back out of the database, so a
     * counted quantity can be handed straight to bcmath at the same scale the column
     * stores. Casting one of these to a float anywhere — including a `(float)` in a DTO
     * on the way to the browser — reintroduces exactly the drift the string discipline
     * exists to prevent.
     *
     * `counted_quantity` and `applied_delta` still come back null when unset: the cast
     * only shapes a value that is there, which is what preserves "not counted yet" as
     * distinct from "counted zero".
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'applied_delta' => 'decimal:4',
        ];
    }
}
