<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Country;
use App\Models\Customer;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Everything but the name is optional, deliberately. See the migration: a customer is
 * usually added long before anyone knows their TIN.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/customer.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class CustomerRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $ignore = $customer instanceof Customer ? $customer->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                // Trashed rows count, matching the unique index — a rule that excluded
                // them would pass here and then fail the INSERT as a 500.
                Rule::unique('customers', 'email')->ignore($ignore),
            ],
            'phone' => ['nullable', 'string', 'max:50'],

            'tin' => ['nullable', 'string', 'max:100'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'sst_registration_no' => ['nullable', 'string', 'max:100'],

            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'state_code' => ['nullable', 'string', 'max:10'],
            // Not `size:2`, which would accept "1!" — and that code would travel
            // straight into an e-invoice. The enum is the list.
            'country_code' => ['nullable', Rule::enum(Country::class)],

            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
