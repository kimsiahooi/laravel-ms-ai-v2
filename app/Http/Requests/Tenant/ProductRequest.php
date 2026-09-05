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
 * `remove_image` is meaningless on create — there is nothing yet to remove — and is
 * declared for both anyway. An unused nullable field costs nothing, while a rule that
 * exists in one mode and not the other is how the two modes drift apart.
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
            // `image` and `mimes` overlap on purpose. `image` refuses anything that is
            // not a picture and says so in those words; `mimes` narrows to the formats
            // every browser can actually display, which rules out the tif somebody's
            // scanner produced. Laravel reports the first failure, so the sentence is
            // always the more specific one that applies.
            //
            // `max` counts kilobytes: 2MB, which is a photo from a phone. The ceiling is
            // here rather than in the media collection because a collection refuses by
            // throwing, and a 500 is not an answer to "that file is too big".
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Sent only when somebody presses Remove on an image that is already stored.
            // Absent is the ordinary case, and it means "leave whatever is there".
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
