<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Unit;
use App\Models\Product;
use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Name, SKU and unit are required, same as a raw material and for the same reason: they
 * are what everything downstream refers to the product by and counts it in.
 *
 * The two foreign keys use {@see ActiveExists} rather than a bare `exists`. A plain
 * `Rule::exists` bypasses the SoftDeletes scope, so a product could be filed under a
 * category the workspace has already deleted.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/product.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class ProductRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $ignore = $product instanceof Product ? $product->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                // Trashed rows count, matching the index — see RawMaterialRequest.
                Rule::unique('products', 'sku')->ignore($ignore),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', ActiveExists::of('categories')],
            'supplier_id' => ['nullable', ActiveExists::of('suppliers')],
            'unit' => ['required', Rule::enum(Unit::class)],
        ];
    }
}
