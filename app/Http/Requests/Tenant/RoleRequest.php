<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Http\Controllers\Tenant\RoleController;
use App\Support\TenantPermissions;
use App\Support\TenantRoles;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * A role: what it is called, and what it reaches. Two fields, one of them 63 checkboxes.
 *
 * **`min:1` is a lockout guard wearing a validation rule's clothes.** A role granting nothing
 * is a role whose holders can sign in and then see an empty sidebar, with no error anywhere to
 * explain it — they have permission to read nothing, which is not a state anybody means to
 * create. Refusing it here puts the message under the matrix, where the fix is.
 *
 * **Every submitted name is checked against the catalog** rather than trusted, so a typo or a
 * crafted request cannot create a permission row that exists and grants nothing. Spatie's
 * `syncPermissions()` would otherwise throw on an unknown name — a 500 where a sentence
 * belongs.
 *
 * **Nothing here protects the built-in Administrator.** The unique rule stops a second role
 * taking its name, and {@see RoleController} refuses to address
 * the row at all — see {@see TenantRoles}.
 */
final class RoleRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            // See {@see TenantFormRequest::uniqueRoleName()} for why the unique rule is
            // behind a helper rather than written out here.
            'name' => [
                'required',
                'string',
                'max:255',
                ...$this->uniqueRoleName($role instanceof Role ? $role->getKey() : null),
            ],
            // `max` is the size of the catalog itself: a role cannot reach more screens than
            // exist, and without a ceiling a crafted request could post a hundred thousand
            // names for `Rule::in` to check one by one.
            'permissions' => ['required', 'array', 'min:1', 'max:'.count(TenantPermissions::names())],
            'permissions.*' => ['string', Rule::in(TenantPermissions::names())],
        ];
    }

    /**
     * The one message the generic wording gets wrong.
     *
     * "The permissions field is required" reads like an empty text box somebody skipped past.
     * What actually happened is a grid of sixty-three boxes with nothing ticked, and the
     * sentence should say what to do about it. Mirrored by `roleSchema` in the browser, so
     * the two layers refuse an empty matrix with the same words.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.required' => __('roles.validation.permissions'),
        ];
    }
}
