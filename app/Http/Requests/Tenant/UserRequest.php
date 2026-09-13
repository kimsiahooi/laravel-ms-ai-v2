<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Actions\SaveUser;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * Adding a colleague, and changing one. Four fields.
 *
 * **The password rule is the same one `admin:create` uses** — `Password::defaults()`, which
 * `AppServiceProvider` sets to six checks in production and to nothing at all outside it. So
 * the two ways a person gets into a workspace agree about what a password is. Two consequences
 * worth knowing rather than discovering: a password this form accepts locally may be refused
 * in production, and in production the `uncompromised()` check makes an HTTP call to Have I
 * Been Pwned while validating.
 *
 * The browser mirror in `lib/validation/schemas/user.ts` deliberately does **not** try to
 * match that. It cannot: the rule differs by environment and one of its checks needs a network
 * call. It applies a floor that is true everywhere and leaves the server as the authority —
 * which is the honest shape for a rule the client cannot evaluate, not a gap to be closed.
 *
 * **The email must stay unique against trashed rows**, matching the index. A rule that
 * excluded them would pass here and then fail the INSERT as a 500. That produces a correct but
 * unhelpful "has already been taken" for a deactivated colleague, so {@see withValidator()}
 * replaces the message with one that says what to do instead.
 */
final class UserRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignore = $user instanceof User ? $user->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Trashed rows count, matching the unique index — see the class note.
                Rule::unique('users', 'email')->ignore($ignore),
            ],
            // By id, because the picker is a ComboboxField over rows and a role's name is
            // something somebody can rename. See {@see TenantFormRequest::roleKey()} for
            // why the constrained rule lives behind a helper.
            'role_id' => ['required', ...$this->roleKey()],
            // Required when adding, optional when editing — a blank box on an edit means
            // "leave their password alone", not "clear it".
            'password' => [
                $ignore === null ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    /**
     * The two messages that are correct but useless without more.
     *
     * **A deactivated colleague's address.** The unique rule is right to refuse it — the index
     * counts trashed rows — but "has already been taken" sends somebody looking for an account
     * that is not in the list they are staring at. Naming the situation is the whole fix.
     *
     * **A role that is not Administrator, for the last administrator.** Checked in
     * {@see SaveUser} too, because that is where it must hold; checked here as
     * well because a person mid-form deserves the message under the field they would change
     * rather than a toast after the fact.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->explainReservedEmail($validator);
            $this->refuseLastAdministratorDemotion($validator);
        });
    }

    /**
     * Swap the unique message when a deactivated person owns that address.
     */
    private function explainReservedEmail(Validator $validator): void
    {
        if (! $validator->errors()->has('email')) {
            return;
        }

        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $trashed = User::query()->onlyTrashed()->where('email', $email)->exists();

        if (! $trashed) {
            return;
        }

        // Replaced rather than added: the original sentence is true and unhelpful, and two
        // messages under one field is a reader deciding which one to believe.
        $validator->errors()->forget('email');
        $validator->errors()->add('email', __('users.validation.email_deactivated'));
    }

    /**
     * Refuse moving the only administrator onto a role that is not Administrator.
     */
    private function refuseLastAdministratorDemotion(Validator $validator): void
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return;
        }

        // `where(...)->first()`, not `find()`: given an array `find()` returns a Collection,
        // so the union it yields has no `name` to read — the same array-where-a-scalar-was-
        // assumed shape `TenantFormRequest::foreignKey()` exists to guard against.
        $role = Role::query()->whereKey($this->input('role_id'))->first();

        // An unknown id is already an `exists` failure; saying so twice helps nobody.
        if ($role === null || $role->name === TenantRoles::ADMIN) {
            return;
        }

        if (! $user->hasRole(TenantRoles::ADMIN) || User::administrators()->count() > 1) {
            return;
        }

        $validator->errors()->add('role_id', __('users.validation.last_administrator'));
    }
}
