<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
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
