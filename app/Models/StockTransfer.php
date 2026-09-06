<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\RecordStockTransfer;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One movement of stock between two warehouses, as a document — see the migration for
 * why the ledger alone cannot say this.
 *
 * No `#[Fillable]` and `$guarded` left at its default, deliberately, for the same reason
 * {@see StockMovement} gives: these rows are never mass-assigned from a request.
 * {@see RecordStockTransfer} is the only thing that writes one, and it names every column.
 *
 * @property int $id
 * @property int $from_warehouse_id
 * @property int $to_warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $quantity a positive magnitude — `from` and `to` carry the direction
 * @property int|null $user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Warehouse $fromWarehouse
 * @property-read Warehouse $toWarehouse
 * @property-read Product|RawMaterial|null $stockable
 * @property-read User|null $user
 */
class StockTransfer extends Model
{
    /**
     * What "find a transfer" means.
     *
     * The same shape as the ledger's, and for the same reason: half of what somebody
     * searches for is not on this table. A transfer is remembered by what moved and
     * which two places it moved between.
     *
     * **Both endpoints match on the warehouse's own name as well as its code and site.**
     * v1 searched code and site only, while its docblock claimed otherwise, so looking
     * for a warehouse by the name written on the door quietly returned nothing.
     *
     * @param  Builder<StockTransfer>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $endpoint = fn (Builder $warehouse) => $warehouse
            ->where('name', 'like', $like)
            ->orWhere('code', 'like', $like)
            ->orWhereHas('location', fn (Builder $site) => $site->where('name', 'like', $like));

        $query->where(function (Builder $group) use ($like, $endpoint): void {
            $group
                ->where('notes', 'like', $like)
                ->orWhereHasMorph(
                    'stockable',
                    [Product::class, RawMaterial::class],
                    fn (Builder $item) => $item
                        ->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like),
                )
                ->orWhereHas('fromWarehouse', $endpoint)
                ->orWhereHas('toWarehouse', $endpoint);
        });
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withTrashed();
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withTrashed();
    }

    /**
     * What moved. `withTrashed` for the reason the ledger gives: the record outlives the
     * catalog, and a transfer that could no longer say what it moved is a puzzle rather
     * than a record. A force-delete still takes the row, so this stays nullable.
     *
     * @return MorphTo<Model, $this>
     */
    public function stockable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // A string, not a float — see StockService on why the whole engine works in
            // decimal strings.
            'quantity' => 'decimal:4',
        ];
    }
}
