<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/supplier.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class SupplierRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $ignore = $supplier instanceof Supplier ? $supplier->getKey() : null;

        return [
            // Not unique — see the migration. Two suppliers may share a name.
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                // Trashed rows count, matching the unique index. A rule that excluded
                // them would pass here and then fail the INSERT as a 500.
                Rule::unique('suppliers', 'email')->ignore($ignore),
            ],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
