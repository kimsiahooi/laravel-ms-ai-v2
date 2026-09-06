<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Unit;
use App\Models\RawMaterial;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Three of the four fields are required, which is unusual for this app and is the
 * point: `sku` is how every later screen refers to this material, and `unit` is what
 * makes its quantities mean anything. A material saved without them would be a row the
 * stock engine cannot use.
 *
 * The `sku` uniqueness check counts trashed rows, matching the index — a rule that
 * excluded them would pass here and then fail the INSERT as a 500. The visible cost is
 * that a deleted material's code stays reserved, and the message does not say so.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/raw-material.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class RawMaterialRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rawMaterial = $this->route('rawMaterial');
        $ignore = $rawMaterial instanceof RawMaterial ? $rawMaterial->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('raw_materials', 'sku')->ignore($ignore),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            // Not `string|max:20`, which would accept "KG" and "kilo" as three
            // different units. The enum is the list, and it is the same list the
            // picker was built from.
            'unit' => ['required', Rule::enum(Unit::class)],
            // What this normally costs, in the workspace's base currency. `gte:0`
            // rather than the default `gt:0`: a material that arrives free with
            // another order costs zero, and refusing that would make it unrecordable.
            //
            // Nullable, and null is not zero — "nobody has said" is a different answer
            // from "it is free", and the purchase order line reads them differently:
            // one leaves the cost box empty to be typed, the other prefills 0.
            'default_cost' => ['nullable', ...$this->decimalRules('gte:0')],
        ];
    }
}
