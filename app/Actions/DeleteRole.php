<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\RoleInUseException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Removes a role, once nobody holds it.
 *
 * **The refusal is the reason this is an Action.** Spatie's own migration puts a cascading
 * foreign key on `model_has_roles`, so deleting a role that people hold succeeds silently and
 * takes their access with it — a colleague who could reach the stock screens yesterday can
 * reach nothing today, and there is no record of why. Counting first turns that into a
 * sentence with a number in it.
 *
 * **Deactivated colleagues count**, which is the whole of {@see User::holdersOf()}: their row
 * survives, their role survives with it, and reactivating them has to put them back where they
 * were. Deleting a role held only by deactivated people would restore them into nothing.
 *
 * **Counted and deleted under one lock.** The lock is on the role row, and it is the FK that
 * makes it work: assigning this role to somebody writes a `model_has_roles` row referencing
 * it, which InnoDB checks by taking a shared lock on the parent — so a concurrent
 * {@see SaveUser} waits rather than slipping a new holder in between the count and the delete.
 *
 * The permission cache needs no explicit clearing here — see {@see SaveRole} for why, and for
 * the one condition that does matter.
 */
final class DeleteRole
{
    /**
     * @throws RoleInUseException when somebody still holds it.
     */
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $locked = Role::query()->whereKey($role->getKey())->lockForUpdate()->first();

            // Already gone — two people pressed at once, or a stale tab. The outcome the
            // caller wanted is the outcome, so there is nothing to say.
            if ($locked === null) {
                return;
            }

            $holders = User::holdersOf($locked)->count();

            if ($holders > 0) {
                throw new RoleInUseException($holders);
            }

            $locked->delete();
        });
    }
}
