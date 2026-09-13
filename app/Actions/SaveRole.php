<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Controllers\Tenant\RoleController;
use App\Http\Requests\Tenant\RoleRequest;
use App\Support\TenantPermissions;
use App\Support\TenantRoles;
use App\Tenancy\PermissionCacheTenancyBootstrapper;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * Creates a role, or changes what an existing one reaches.
 *
 * **Everything in one transaction**, because a role is two writes: the row, and the set of
 * permissions attached to it. A create that saved the name and then failed to attach anything
 * would leave a role granting nothing, which the editor cannot produce
 * ({@see RoleRequest} requires at least one) and which would then be
 * un-fixable without noticing it exists.
 *
 * **The permission cache clears itself, and that is verified rather than assumed.** Spatie's
 * `Role` uses {@see RefreshesPermissionCache}, which forgets the registrar's cache on every
 * `saved` and `deleted` event, and `syncPermissions()` ends in `givePermissionTo()` which
 * forgets it again for a `Role` specifically. So no explicit
 * `app(PermissionRegistrar::class)->forgetCachedPermissions()` belongs here — adding one would
 * suggest the writes do not do it, which is the thing a future reader would then have to
 * check. What still matters is that the cache key is tenant-scoped only while tenancy is
 * initialised ({@see PermissionCacheTenancyBootstrapper}), so this is a
 * request-time Action and never a central-context command.
 *
 * **Nothing here guards the built-in Administrator.** That role is refused at the door with a
 * 403 by {@see RoleController}, because being untouchable is a
 * fact about who may address the row at all rather than about what the payload says — the same
 * register a permission check answers in. See {@see TenantRoles}.
 */
final class SaveRole
{
    /**
     * The guard every role in a workspace belongs to.
     *
     * One guard, so this is a constant rather than a field on the form: a role on another
     * guard could never be assigned to a `User` and would sit in the list doing nothing.
     */
    private const GUARD = 'web';

    /**
     * @param  array{name: string, permissions: list<string>}  $fields
     *                                                                  `permissions` are seeded names — `categories.view` — already checked
     *                                                                  against {@see TenantPermissions::names()} by the request.
     * @param  Role|null  $role  the role being changed, or null to create one.
     */
    public function handle(array $fields, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($fields, $role): Role {
            $saved = $role === null
                ? Role::query()->create(['name' => $fields['name'], 'guard_name' => self::GUARD])
                : $this->rename($role, $fields['name']);

            // Replaces the set rather than adding to it, which is what the editor means: the
            // boxes on screen are the whole answer, and a box cleared has to revoke.
            $saved->syncPermissions($fields['permissions']);

            return $saved;
        });
    }

    /**
     * Change a role's name, under a lock.
     *
     * The lock is not about the name — two people renaming the same role is a last-writer-wins
     * that nobody is harmed by. It is about {@see DeleteRole}, which counts holders under a
     * lock on this same row before removing it: taking the row here means a rename and a
     * delete cannot interleave into a role that is saved and then vanishes.
     */
    private function rename(Role $role, string $name): Role
    {
        $locked = Role::query()->whereKey($role->getKey())->lockForUpdate()->first() ?? $role;

        $locked->forceFill(['name' => $name])->save();

        return $locked;
    }
}
