<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Controllers\Tenant\ProductController;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a product's bill of materials: `quantity` of a raw material per unit of
 * the product.
 *
 * Deliberately thin. Unlike every other model in the catalog it has no soft delete, no
 * `Searchable` scope and no creator — a line is never looked up on its own, never
 * searched for, and never restored. It exists only as part of a bill, which
 * {@see ProductController::updateBom()} replaces wholesale.
 *
 * `quantity` is a string, not a float. The `decimal:4` cast returns one on purpose:
 * decimal(15,4) values get multiplied by order sizes and summed across a bill, and a
 * float cannot hold 0.1 exactly. Anything that does arithmetic on this should reach for
 * a decimal type, not `(float)`.
 *
 * @property int $id
 * @property int $product_id
 * @property int $raw_material_id
 * @property string $quantity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Product $product
 * @property-read RawMaterial $rawMaterial
 */
#[Fillable(['raw_material_id', 'quantity'])]
final class BomItem extends Model
{
    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<RawMaterial, $this>
     */
    public function rawMaterial(): BelongsTo
    {
        // withTrashed, matching Product::category(): the FK cascades on a hard delete,
        // but the materials screen soft-deletes, so without this a bill referencing a
        // deleted material would resolve to null and the line would lose its name.
        return $this->belongsTo(RawMaterial::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
