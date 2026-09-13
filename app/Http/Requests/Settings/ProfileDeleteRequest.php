<?php

namespace App\Http\Requests\Settings;

use App\Actions\DeactivateUser;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Deleting your own account, from Settings → Profile.
 *
 * **This carries a lockout guard, and it is the only thing standing between a workspace and
 * being unreachable.** `profile.destroy` is deliberately unmapped in `TenantPermissions` —
 * it is a personal route, so no permission gates it — which means the sole administrator of a
 * workspace can delete themselves from a screen that asks only for their password. The row
 * soft-deletes, and because the `users` email index counts trashed rows, they cannot even sign
 * up again. Recovery is a super-admin at `/admin` or a database edit.
 *
 * The guard lives here rather than in an Action because the person is standing in a form with
 * a password field: a validation error under the box they just filled is the right register,
 * and it is the same register the rest of this screen already speaks. The Users screen's
 * equivalent refusal lives in {@see DeactivateUser} instead, because that one has
 * to hold for any caller.
 */
class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Refuse when this would leave the workspace with no administrator.
     *
     * On `password`, because that is the only field on the form — there is nowhere else for
     * the sentence to land, and it is the control the reader is looking at.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User || ! $user->hasRole(TenantRoles::ADMIN)) {
                return;
            }

            if (User::administrators()->count() > 1) {
                return;
            }

            $validator->errors()->add('password', __('users.error.last_administrator_self'));
        });
    }
}
