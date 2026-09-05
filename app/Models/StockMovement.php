<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the stock ledger.
 *
 * Append-only: nothing updates or deletes one of these. A mistake is corrected by
 * writing the opposite movement, which is what keeps the history readable afterwards.
 *
 * No `#[Fillable]` and `$guarded` left at its default, deliberately: these rows are
 * never mass-assigned from a request. {@see StockService} is the only
 * thing that writes them, and it names every column.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $quantity signed — positive in, negative out
 * @property StockMovementReason $reason
 * @property int|null $user_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Product|RawMaterial|null $stockable
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    /**
     * What "find a movement" means.
     *
     * Not the {@see Searchable} trait, and the difference is that half of what somebody
     * searches for is not on this table. A movement is remembered by what moved and
     * where it went, so the item's name and sku and the warehouse's name and code have
     * to be reachable — and `stockable` is a morph, which no list of local columns can
     * express.
     *
     * `whereHasMorph` rather than a join, so a movement appears once. The two stockable
     * tables could not be joined to in a single pass anyway.
     *
     * The reason is deliberately not searched: it is a code like `transfer_out`, and
     * matching English against it would work in one locale and silently not in the
     * other two. The reason filter is the control for that question.
     *
     * @param  Builder<StockMovement>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('notes', 'like', $like)
                ->orWhereHasMorph(
                    'stockable',
                    [Product::class, RawMaterial::class],
                    fn (Builder $item) => $item
                        ->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like),
                )
                ->orWhereHas(
                    'warehouse',
                    fn (Builder $warehouse) => $warehouse
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like),
                );
        });
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /**
     * What moved — a product or a raw material.
     *
     * `withTrashed`, because the ledger is append-only and outlives the catalog. A
     * product deleted from the catalog is soft-deleted, and a movement that could no
     * longer say what it moved would turn a record into a puzzle. A *force*-delete does
     * take the row, which is why the relation is still nullable and the DTO renders a
     * dash rather than assuming.
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
            // A string, not a float. See StockService on why the whole engine works in
            // decimal strings; casting here to `decimal:4` keeps that true on the way
            // back out of the database.
            'quantity' => 'decimal:4',
            'reason' => StockMovementReason::class,
        ];
    }
}
