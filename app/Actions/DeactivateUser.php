<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Support\TenantRoles;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Stops somebody signing in, without forgetting they were here.
 *
 * A soft delete, so every order they raised still names them and their email stays reserved —
 * which is why adding "a new person" with a deactivated colleague's address has to offer
 * reactivation rather than reporting the address as taken. See {@see User}.
 *
 * **Two refusals, and neither is a permission check.** Somebody with `users.delete` is
 * entitled to deactivate people; these are about the workspace surviving the act:
 *
 * - **Not yourself.** Signing out is a button; removing your own ability to sign back in is
 *   not something a person means to do, and there is no undo from the other side of it.
 * - **Not the last administrator.** The invariant {@see User::administrators()} defines: a
 *   workspace must always keep somebody who can fix anything. Without it the recovery is a
 *   super-admin at `/admin` or a database edit.
 *
 * Both live here rather than in the controller because they must hold from any caller, and
 * because the count and the write belong in one transaction — see {@see SaveUser} on why that
 * matters more for the shape than for the stakes.
 */
final class DeactivateUser
{
    /**
     * @param  User  $user  the person being deactivated
     * @param  User|null  $actor  whoever pressed the button, or null from a console
     *
     * @throws DomainException when this is the actor themselves, or the last administrator
     */
    public function handle(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            // Already gone — two people pressed at once, or a stale tab. Nothing to do and
            // nothing to say: the outcome the caller wanted is the outcome.
            if ($locked === null) {
                return;
            }

            if ($actor !== null && $actor->is($locked)) {
                throw new DomainException('A person cannot deactivate their own account.');
            }

            if ($locked->hasRole(TenantRoles::ADMIN) && User::administrators()->count() <= 1) {
                throw new DomainException('A workspace must keep at least one administrator.');
            }

            $locked->delete();
        });
    }

    /**
     * Let somebody back in.
     *
     * No guard at all, deliberately: restoring cannot lock a workspace out, and the email that
     * was reserved while they were away is theirs again. The permission to do it is
     * `users.update` rather than a power of its own — see `TenantPermissions::ROUTE_OVERRIDES`.
     */
    public function restore(User $user): void
    {
        $user->restore();
    }
}
