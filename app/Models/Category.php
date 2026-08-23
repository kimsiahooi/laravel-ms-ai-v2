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
 * A product category. Per-tenant, on the default connection — which
 * InitializeTenancyByPath has already repointed at this workspace's database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 */
#[Fillable(['name', 'description'])]
class Category extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a category" means, wherever categories are being listed.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'description'];
    }
}
