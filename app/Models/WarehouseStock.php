<?php

declare(strict_types=1);

namespace App\Models;

use App\Console\Commands\HammerStock;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * On-hand for one item in one warehouse — the running total of the ledger.
 *
 * Derived data. If this table were lost it could be rebuilt exactly by summing
 * `stock_movements`, and {@see HammerStock} does precisely that
 * to check the two still agree.
 *
 * Only {@see StockService} writes here, and never outside a transaction
 * that also appends the matching ledger row.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property string $stockable_type
 * @property int $stockable_id
 * @property string $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Product|RawMaterial $stockable
 */
class WarehouseStock extends Model
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
        return ['quantity' => 'decimal:4'];
    }
}
