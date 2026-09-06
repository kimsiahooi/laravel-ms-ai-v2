<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\WarehouseInventory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The level at which one item in one warehouse wants restocking.
 *
 * Read almost entirely through {@see WarehouseInventory}, which joins it to on-hand
 * so a screen can say both numbers at once — the threshold on its own answers nothing.
 * This class exists for the write side: one `updateOrCreate` per decision, and a
 * `delete` when the level is cleared.
 *
 * **A stored row always means a real threshold.** Zero is not stored — see the
 * migration — so nothing that reads this table has to remember to exclude it.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $min_stock
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Product|RawMaterial $stockable
 */
#[Fillable(['warehouse_id', 'stockable_type', 'stockable_id', 'min_stock'])]
class WarehouseReorderLevel extends Model
{
    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['min_stock' => 'decimal:4'];
    }
}
