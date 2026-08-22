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
 *
 * There is deliberately no `attributes()` here: the field names live in
 * `lang/{locale}/validation.php` under `attributes`, where Laravel reads them without
 * being asked and the zod schema reads the very same keys. One source, both layers.
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('console.validation.slug_regex'),
            'slug.not_in' => __('console.validation.slug_reserved'),
            'slug.unique' => __('console.validation.slug_taken'),
        ];
    }
}
