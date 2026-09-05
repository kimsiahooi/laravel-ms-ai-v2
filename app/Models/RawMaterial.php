<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A raw material in the workspace's catalog. Per-tenant, on the default connection —
 * which InitializeTenancyByPath has already repointed at this workspace's database.
 *
 * @property int $id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property Unit $unit
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 * @property-read Collection<int, Product> $products
 */
#[Fillable(['name', 'sku', 'barcode', 'unit'])]
class RawMaterial extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a material" means: what it is called, and the two codes it is
     * identified by.
     *
     * The barcode is here even though a supplier's tax ID deliberately is not, and the
     * difference is how the value gets into the box. Nobody types a barcode — it is
     * scanned, whole, and a scan that found nothing is a scan that failed. A tax ID is
     * typed from memory, where a partial match on a fragment of some other number is a
     * wrong answer dressed as a right one.
     *
     * `unit` is absent: searching for "kg" would return every material measured in
     * kilograms, which is a filter nobody asked for by typing in a search box.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    /**
     * The products whose bill of materials calls for this material.
     *
     * `bom_items` is a pivot with a payload — the per-unit quantity — so it has its own
     * model for the editor to write through ({@see BomItem}). Read the other way it is
     * an ordinary many-to-many, and that is all this needs to answer: "is anything
     * still using this?", which is what the delete guard asks.
     *
     * Trashed products are excluded, by Product's own SoftDeletes scope. That is
     * deliberate rather than incidental: a soft-deleted product has no restore or
     * force-delete route, so counting its bill here would make a material undeletable
     * forever with nothing on any screen to explain why.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bom_items')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // The column holds 'kg'; everything that reads it gets a Unit, with the
        // dimension and conversion factor attached. Validation already refuses anything
        // outside the enum, so the cast can never meet a value it cannot resolve.
        return ['unit' => Unit::class];
    }
}
