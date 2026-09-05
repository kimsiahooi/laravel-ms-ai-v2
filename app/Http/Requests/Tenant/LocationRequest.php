<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Location;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/location.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class LocationRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                // Unique within this workspace — the database IS the tenant, so no
                // further qualification is needed. Ignores itself on update.
                //
                // Trashed rows count, deliberately: MySQL's unique index counts them
                // too, and a rule that excluded them would pass validation and then
                // fail the INSERT as a 500.
                Rule::unique('locations', 'code')
                    ->ignore($location instanceof Location ? $location->getKey() : null),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
