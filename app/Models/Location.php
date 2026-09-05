<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A site — a branch, an outlet, a factory. Per-tenant, on the default connection,
 * which InitializeTenancyByPath has already repointed at this workspace's database.
 *
 * The top of the storage hierarchy: a site owns warehouses, and a warehouse is what
 * actually holds stock. Nothing is stored *at* a site directly, which is why this
 * table has no quantities on it.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 */
#[Fillable(['name', 'code', 'address'])]
class Location extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a site" means: what it is called, the code it is filed under, and
     * where it is — someone looking for the Penang branch is as likely to type the
     * town as the name anyone gave it.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'code', 'address'];
    }
}
