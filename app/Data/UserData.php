<?php

declare(strict_types=1);

namespace App\Data;

use App\Actions\DeactivateUser;
use App\Models\User;
use App\Support\TenantRoles;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One person in a workspace, as the Users list reads them.
 *
 * **No password, no two-factor secret, no recovery codes** — and that is not merely the
 * model's `#[Hidden]` doing its job, it is this class naming every field it sends. A Data
 * object is an allow-list; an accidental `->toArray()` on the model is not.
 *
 * **`role` is a single name, because a user holds exactly one.** Spatie supports many and
 * this app deliberately does not: with two, the permissions somebody actually has are a union
 * nobody can read off the screen, and every lockout guard has to reason about that union
 * instead of about a role. One role is a picker; several is a second matrix.
 *
 * **Three of these fields are about what an administrator may do to the row**, and all three
 * are the server's answer rather than the browser's:
 *
 * - `is_self` — you may not deactivate your own account. The UI hides the action and
 *   {@see DeactivateUser} refuses it regardless, because hiding is not a guard.
 * - `is_last_administrator` — deactivating or demoting this person would leave the workspace
 *   with nobody who can fix anything. Computed against `User::administrators()`, which is the
 *   one definition of that invariant.
 * - `deleted_at` — a deactivated person keeps their row, cannot sign in, and keeps their email
 *   reserved. See the `User` model on why that last part matters when somebody tries to add
 *   them again.
 */
#[TypeScript]
final class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        /** Null only for a row whose role was deleted out from under it. */
        public ?string $role,
        /** What the form's picker seeds from. Null in the same one case as `role`. */
        public ?int $role_id,
        /**
         * Still using the password an administrator typed for them.
         *
         * Shown in the list because it is the difference between somebody who has joined and
         * somebody who has merely been added, and because an administrator knowing a password
         * is a state that should look temporary on screen.
         */
        public bool $must_change_password,
        /** Set once deactivated: the person cannot sign in, and the row stays. */
        public ?string $deleted_at,
        /** Whether this row is the person reading the screen. */
        public bool $is_self,
        /** Whether deactivating or demoting this person would lock the workspace out. */
        public bool $is_last_administrator,
        public string $created_at,
    ) {}

    /**
     * @param  User|null  $actor  the signed-in person, for `is_self`. Null for a console
     *                            context, where nobody is reading a screen and every row is
     *                            somebody else's.
     * @param  int  $administrators  how many active administrators the workspace has, counted
     *                               once by the caller rather than once per row — twenty users
     *                               would otherwise be twenty identical counts.
     */
    public static function fromUser(User $user, ?User $actor, int $administrators): self
    {
        $first = $user->roles->first();
        $role = $first instanceof Role ? $first : null;

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            // `getRoleNames()` reads the loaded relation, so the caller eager-loads `roles`
            // or this is a query per row.
            // Narrowed rather than read off the relation directly: spatie types `roles` as
            // a collection of `Model`, so `->name` on it is an assumption rather than a fact.
            role: $role?->name,
            role_id: $role === null ? null : (int) $role->getKey(),
            must_change_password: $user->must_change_password,
            deleted_at: $user->deleted_at?->toIso8601String(),
            is_self: $actor !== null && $actor->is($user),
            // An administrator who is the only one left. A deactivated row is not counted as
            // an administrator by the scope, so a deactivated one can never be "the last".
            is_last_administrator: $administrators <= 1
                && $user->deleted_at === null
                && $user->hasRole(TenantRoles::ADMIN),
            created_at: $user->created_at?->toIso8601String() ?? '',
        );
    }
}
