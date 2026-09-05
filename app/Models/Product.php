<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unit;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
 * @property-read Collection<int, BomItem> $bomItems
 * @property-read Supplier|null $supplier
 * @property-read User|null $creator
 */
#[Fillable([
    'name', 'sku', 'barcode', 'description',
    'category_id', 'supplier_id', 'unit',
])]
class Product extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /** The media collection holding the photo. */
    public const IMAGE = 'image';

    /** The conversion the listing and the dialog preview are served from. */
    public const THUMB = 'thumb';

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

    /**
     * What goes into it: the raw materials and the per-unit quantity of each.
     *
     * Ordered by id, which is insertion order — and since updateBom() rewrites the
     * whole bill on every save, that is the order the lines were left in by whoever
     * last edited it. Sorting by material name instead would silently reshuffle the
     * editor under someone who had just arranged it.
     *
     * `rawMaterial` on the other side is nullable in practice: the FK cascades on a
     * hard delete, but the materials screen soft-deletes, so a line can point at a
     * trashed material. The DTO resolves the name `withTrashed()` rather than showing
     * a blank where a material used to be.
     *
     * @return HasMany<BomItem, $this>
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('id');
    }

    /**
     * The photo. One, not many — `singleFile()` means a second upload replaces the
     * first and deletes its file, so nobody has to remember to clear the old one and a
     * product cannot quietly accumulate five years of packaging revisions.
     *
     * What may be uploaded is decided by ProductRequest, not here. A collection can
     * refuse a mime type too, but it does so by throwing — a 500 where the form would
     * have shown "must be an image" under the field.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE)->singleFile();
    }

    /**
     * One resized copy, generated on upload.
     *
     * The listing shows twenty-five products at 40px square. Serving the originals into
     * those cells means up to 50MB of photographs to draw two megapixels of thumbnail,
     * on every page of the catalog — the single most-visited screen in the module.
     *
     * 256px covers every place a stored image is currently shown: the table at 40px on a
     * 3x display, and the edit dialog's preview at 128px on a 2x one. `Fit::Max` never
     * upscales, so a small logo-like image is left exactly as it was rather than being
     * blown up and re-encoded.
     *
     * `keepOriginalImageFormat()` is not optional here, though it looks like a detail.
     * medialibrary's conversions default to JPEG, which has no alpha channel — so a
     * product photographed on a transparent background (which is most catalog artwork)
     * comes back as the product on a solid BLACK square. Measured, not guessed: a
     * transparent PNG through the default pipeline gives rgb(0,0,0) in every corner.
     *
     * It is generated inline, during the upload request. config/media-library.php says
     * why a queued one would never run here.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // `keepOriginalImageFormat()` first, and not only because it reads better: it is
        // declared on Conversion, while `fit()` and `quality()` are forwarded to the image
        // driver, so a call after them is a call on the driver's type.
        $this->addMediaConversion(self::THUMB)
            ->keepOriginalImageFormat()
            ->fit(Fit::Max, 256, 256)
            ->quality(80);
    }
}
