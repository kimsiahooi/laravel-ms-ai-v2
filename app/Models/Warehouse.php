<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A warehouse — the building stock actually sits in, belonging to a site.
 *
 * This is the level everything stocked is addressed at: a movement moves through a
 * warehouse, not through a site. The table still holds no quantities of its own —
 * those arrive with `warehouse_stocks` and StockService.
 *
 * @property int $id
 * @property int $location_id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Location $location
 * @property-read User|null $creator
 */
#[Fillable(['location_id', 'name', 'code', 'address'])]
class Warehouse extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * The site this building stands on.
     *
     * `withTrashed`: the FK is NOT NULL and restricted, so the row cannot vanish under
     * a live warehouse — but a site trashed while its last warehouse was already
     * trashed would leave the relation empty, and a list that cannot name a
     * warehouse's site is worse than one naming a site since removed.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * What "find a warehouse" means. The site's name is deliberately not in here:
     * searching "Penang" would return every warehouse on that site, which is what the
     * site filter is for — and a search box that silently behaves like a filter is a
     * search box nobody can predict.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'code', 'address'];
    }
}
