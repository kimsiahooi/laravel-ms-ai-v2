<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A product — what the workspace sells. Per-tenant, on the default connection, which
 * InitializeTenancyByPath has already repointed at this workspace's database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property int|null $category_id
 * @property int|null $supplier_id
 * @property Unit $unit
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Category|null $category
 * @property-read Supplier|null $supplier
 * @property-read User|null $creator
 */
#[Fillable([
    'name', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'unit',
])]
class Product extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a product" means: what it is called, and the two codes it is
     * identified by — the same three as a raw material, for the same reasons.
     *
     * `description` is absent even though it is prose and would seem searchable. It is
     * a paragraph, and a LIKE across it turns a search for a name into a search for any
     * product that happens to mention that word.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit' => Unit::class];
    }

    /**
     * Where this product files. Nullable, and the row it points at may itself be
     * trashed — see the migration on why nullOnDelete does not fire on a soft delete.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * Who supplies it. Nullable, and trashed rows resolve for the same reason.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
