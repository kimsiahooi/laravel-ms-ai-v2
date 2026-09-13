<?php

declare(strict_types=1);

namespace App\Data;

use App\Http\Controllers\Tenant\RoleController;
use App\Models\User;
use App\Support\TenantPermissions;
use App\Support\TenantRoles;
use Spatie\LaravelData\Data;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One role: its name, everything it reaches, and how many people hold it.
 *
 * **One shape for both screens, deliberately.** The list needs the name and the two counts;
 * the editor needs the permission names to tick. A workspace has a handful of roles rather
 * than a page of them, so sending the names to the list costs a few hundred short strings and
 * saves a second Data class that would have to be kept in step with this one.
 *
 * **`permissions` is names, not labels.** They are the seeded strings — `categories.view` —
 * which is what the editor compares its checkboxes against and what the request posts back.
 * The words a person reads are composed in the browser from
 * {@see TenantPermissions::matrix()}; nothing translatable travels on this object.
 *
 * **`holders` counts deactivated colleagues too**, and that is the whole of the definition: a
 * deactivated person still holds their role, and reactivating them needs it to still exist.
 * Counting only active people would show one number on the card and refuse the delete with a
 * different one. See {@see User::holdersOf()}.
 *
 * **`is_locked` is the built-in Administrator**, the one role that always holds the entire
 * catalog and that nothing in the UI may edit or delete — see {@see TenantRoles}. It is the
 * server's answer about the row rather than about the reader: a workspace where this could be
 * edited is a workspace that can lock itself out. {@see RoleController} refuses it again with
 * a 403, because a hidden menu item is not a guard.
 */
#[TypeScript]
final class RoleData extends Data
{
    public function __construct(
        public int $id,
        /** The workspace's own word for this role. Never translated — see the controller. */
        public string $name,
        /** @var list<string> The seeded permission names it grants. */
        public array $permissions,
        /** How many people hold it, deactivated colleagues included. */
        public int $holders,
        /** The built-in Administrator, which cannot be edited or deleted. */
        public bool $is_locked,
        public string $created_at,
    ) {}

    /**
     * @param  int  $holders  counted by the caller, so a list of roles is one query rather
     *                        than one per row.
     */
    public static function fromRole(Role $role, int $holders): self
    {
        return new self(
            id: (int) $role->getKey(),
            name: $role->name,
            permissions: self::permissionNames($role),
            holders: $holders,
            is_locked: $role->name === TenantRoles::ADMIN,
            created_at: $role->created_at?->toIso8601String() ?? '',
        );
    }

    /**
     * The permission names on a loaded role.
     *
     * The caller eager-loads `permissions` or this is a query per role. Unlike a user's
     * roles — which spatie types as a collection of bare `Model`, hence the narrowing in
     * {@see UserData} — a role's permissions are typed, so the name can simply be read.
     *
     * @return list<string>
     */
    private static function permissionNames(Role $role): array
    {
        return array_values(
            $role->permissions
                ->map(static fn (Permission $permission): string => $permission->name)
                ->all(),
        );
    }
}
