<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Warehouse;
use App\Support\ActiveExists;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/warehouse.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class WarehouseRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            // Required, unlike a product's category: a warehouse with no site is not
            // addressable, and the column is NOT NULL. ActiveExists rather than plain
            // exists, so a trashed site cannot be submitted by hand — see the class.
            'location_id' => ['required', ActiveExists::of('locations')],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                // Unique across the workspace, not within the site. See the migration.
                // Trashed rows count, as everywhere else: MySQL's index counts them,
                // and a rule that did not would pass here and fail the INSERT as a 500.
                Rule::unique('warehouses', 'code')
                    ->ignore($warehouse instanceof Warehouse ? $warehouse->getKey() : null),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
