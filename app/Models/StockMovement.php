<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use App\Services\StockService;
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
 * @property-read Product|RawMaterial $stockable
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function stockable(): MorphTo
    {
        // withTrashed on a morph is set per-type by the caller; a movement must be able
        // to name what moved even after the product has been removed from the catalog.
        return $this->morphTo();
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
