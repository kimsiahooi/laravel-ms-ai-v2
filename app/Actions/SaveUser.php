<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\UserData;
use App\Models\User;
use App\Support\TenantRoles;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds a colleague, or changes one — name, email, role, and the password an administrator
 * types for them.
 *
 * **One role, assigned with `syncRoles`.** See {@see UserData} on why a person holds
 * exactly one.
 *
 * **The demotion guard lives here rather than in the request**, because it is a fact about
 * other rows at this instant rather than about the payload: moving the last administrator onto
 * another role leaves nobody who can fix anything, and no amount of inspecting the submitted
 * fields can see that. A request rule would have to count rows, which is this class's job.
 *
 * **Counted and written inside one transaction, with the row locked.** v1 read
 * `count() <= 1` outside any transaction, so two administrators demoting each other at the
 * same moment both saw two and both succeeded. The stakes are lower than a stock movement's —
 * the recovery is a super-admin at `/admin` rather than a miscounted shelf — but it is the
 * identical shape this codebase has already fixed in {@see FulfillSalesOrder},
 * {@see ReceivePurchaseOrder} and {@see PostStockTake}, and doing it differently here would
 * make this the odd one out rather than the pragmatic one.
 */
final class SaveUser
{
    /**
     * @param  array{name: string, email: string, role_id: int, password: string|null}  $fields
     *                                                                                           `password` null on an edit means "leave it alone".
     * @param  User|null  $user  the person being changed, or null to add one.
     *
     * @throws DomainException when this would leave the workspace with no administrator.
     */
    public function handle(array $fields, ?User $user = null): User
    {
        return DB::transaction(function () use ($fields, $user): User {
            $saved = $user === null
                ? $this->add($fields)
                : $this->revise($fields, $user);

            // Spatie clears its own cache when a *role's* permissions change, but not when a
            // user's roles do — correctly, because user↔role assignments are not in that
            // cache. This call is here for the case that is: nothing, today. It is cheap, it
            // is what RolesSeeder does, and the day somebody grants a permission directly to
            // a user rather than through a role, its absence would be a gate still enforcing
            // yesterday's answer.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $saved;
        });
    }

    /**
     * A new colleague, with the password an administrator typed.
     *
     * `must_change_password` is forced true on every creation, not offered as a choice. The
     * administrator knows this password; the person it belongs to did not choose it. See the
     * migration.
     *
     * @param  array{name: string, email: string, role_id: int, password: string|null}  $fields
     */
    private function add(array $fields): User
    {
        $user = new User;

        // forceFill, because `must_change_password` is deliberately not fillable — see the
        // model. The other three are, and are named here anyway: this is the one place a user
        // row is written from a request, so it should say exactly what it sets.
        $user->forceFill([
            'name' => $fields['name'],
            'email' => $fields['email'],
            // Hashed by the model's `password` cast, not here — one place decides how, the
            // same rule `admin:create` follows.
            'password' => $fields['password'],
            'must_change_password' => true,
        ])->save();

        $user->syncRoles([self::role($fields['role_id'])]);

        return $user;
    }

    /**
     * An existing colleague. A blank password leaves theirs alone rather than clearing it.
     *
     * @param  array{name: string, email: string, role_id: int, password: string|null}  $fields
     *
     * @throws DomainException
     */
    private function revise(array $fields, User $user): User
    {
        $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first() ?? $user;

        $this->refuseIfLastAdministrator($locked, self::role($fields['role_id']));

        $changes = [
            'name' => $fields['name'],
            'email' => $fields['email'],
        ];

        // Only when one was typed. An administrator resetting somebody's password puts them
        // back into the same state a new colleague starts in — they know it, so the person
        // it belongs to has to replace it.
        if ($fields['password'] !== null) {
            $changes['password'] = $fields['password'];
            $changes['must_change_password'] = true;
        }

        $locked->forceFill($changes)->save();

        $locked->syncRoles([self::role($fields['role_id'])]);

        return $locked;
    }

    /**
     * The role a submitted id names.
     *
     * `findOrFail`, not `find`: the id has already passed an `exists` rule, so a miss here
     * means the row went between validating and writing — which is a 404 rather than a
     * silently role-less user.
     */
    private static function role(int $id): Role
    {
        return Role::query()->where('guard_name', 'web')->findOrFail($id);
    }

    /**
     * Refuse a role change that would leave the workspace with no administrator.
     *
     * Only bites when three things are true at once: this person is an administrator, they are
     * the only one, and the role they are moving to is not Administrator. Moving the last
     * administrator to Administrator is a no-op, not a lockout.
     *
     * @throws DomainException
     */
    private function refuseIfLastAdministrator(User $user, Role $role): void
    {
        if ($role->name === TenantRoles::ADMIN || ! $user->hasRole(TenantRoles::ADMIN)) {
            return;
        }

        if (User::administrators()->count() <= 1) {
            throw new DomainException('A workspace must keep at least one administrator.');
        }
    }
}
