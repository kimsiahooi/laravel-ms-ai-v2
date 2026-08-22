<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Support\ReservedSlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Provisioning a workspace. Mirrored in the browser by
 * resources/js/lib/validation/schemas/store-tenant.ts — `bun run check:validation`
 * fails if the two stop agreeing on which fields are checked.
 */
final class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is already gated by auth:central; belt-and-suspenders.
        return $this->user('central') !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                // Capped so `<db prefix><slug>` fits MySQL's 64-char database-name limit.
                'max:50',
                // Lowercase kebab only — must match the {tenant} route pattern, or the
                // provisioned workspace would not be reachable by URL.
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(ReservedSlugs::LIST),
                Rule::unique('tenants', 'id'),
            ],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Plain names for the administrator fields. Without these Laravel says "the admin
     * email field", which reads like a different thing from the "Administrator" the
     * form asks for — and the zod schema mirrors these, so both layers say the same
     * sentence for the same mistake.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'admin_name' => 'administrator name',
            'admin_email' => 'administrator email',
            'admin_password' => 'administrator password',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'slug.not_in' => 'That slug is reserved and cannot be used.',
            'slug.unique' => 'A workspace with that slug already exists (it may be archived — restore or permanently delete it first).',
        ];
    }
}
