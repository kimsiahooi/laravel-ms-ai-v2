<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\DeleteRole;
use App\Actions\SaveRole;
use App\Data\RoleData;
use App\Exceptions\RoleInUseException;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Middleware\AuthorizeTenantRoute;
use App\Http\Requests\Tenant\RoleRequest;
use App\Http\Requests\Tenant\TenantFormRequest;
use App\Models\User;
use App\Support\TenantPermissions;
use App\Support\TenantRoles;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * What a role reaches — the screen the whole permission catalog was built for.
 *
 * **A page, not a dialog**, which is the opposite of every other short form in this app and is
 * decided by the shape rather than the field count: a name and nineteen groups of checkboxes
 * is a grid, and a dialog would have to be scrolled to reach its own submit button. That is
 * only safe because {@see TenantPermissions::routeMap()} auto-maps `{screen}.create` and
 * `{screen}.edit` — before it did, a GET form page was a route name nothing guarded, which is
 * how two catalogs came to be readable by anybody with a login.
 *
 * **The list is not a `DataTable`, and that is deliberate.** A workspace has a handful of
 * roles; a search box, a pagination bar and a column picker over five rows is furniture around
 * nothing. So there is no `TableKey` case for roles either — do not add one to "fix" the
 * inconsistency.
 *
 * **The built-in Administrator is refused here, with a 403 rather than a message.** It always
 * holds the whole catalog and cannot be edited, renamed or deleted — see {@see TenantRoles}.
 * Hiding the menu items is for the reader; this is the guard, because a hidden item is still
 * reachable by anybody willing to craft a request. Its existence is also what makes several
 * other invariants free: because it is locked and {@see User::administrators()} guarantees at
 * least one active holder, `users.update` and `roles.update` are always held by somebody. So
 * there is no "you have removed the last role that can manage users" guard in this file —
 * not because it was forgotten, but because nothing can reach that state.
 *
 * **`roles.create` and `roles.update` are the keys to the workspace, and that is inherent.**
 * Somebody who can edit roles can write themselves a role holding anything, so granting those
 * two permissions is granting everything eventually. That is true of every system where role
 * editing is itself a permission; the catalog's answer is that only Administrator holds them
 * until a workspace deliberately says otherwise.
 *
 * Every write redirects rather than rendering, so `HandleInertiaRequests`' `auth.permissions`
 * closure runs again — a role edit that changed your own access must not leave the browser
 * holding yesterday's list. See {@see AuthorizeTenantRoute} for the server half.
 */
final class RoleController
{
    use RespondsWithToast;

    /** Every role in the workspace, with what it reaches and how many people hold it. */
    public function index(): Response
    {
        return Inertia::render('roles/index', [
            'roles' => self::roles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/form', [
            'role' => null,
            'groups' => TenantPermissions::matrix(),
        ]);
    }

    public function store(RoleRequest $request, SaveRole $save): RedirectResponse
    {
        $role = $save->handle(self::fields($request));

        $this->toast(__('roles.toast.created', ['name' => $role->name]));

        return to_route('roles.index');
    }

    public function edit(Role $role): Response
    {
        self::refuseBuiltIn($role);

        $role->load('permissions');

        return Inertia::render('roles/form', [
            'role' => RoleData::fromRole($role, User::holdersOf($role)->count()),
            'groups' => TenantPermissions::matrix(),
        ]);
    }

    public function update(RoleRequest $request, Role $role, SaveRole $save): RedirectResponse
    {
        self::refuseBuiltIn($role);

        $save->handle(self::fields($request), $role);

        $this->toast(__('roles.toast.updated', ['name' => $role->name]));

        return to_route('roles.index');
    }

    /** Remove a role, once nobody holds it. */
    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        self::refuseBuiltIn($role);

        try {
            $delete->handle($role);
        } catch (RoleInUseException $refusal) {
            $this->toast(
                trans_choice('roles.error.in_use', $refusal->holders, ['count' => $refusal->holders]),
                'error',
            );

            return back();
        }

        $this->toast(__('roles.toast.deleted', ['name' => $role->name]));

        return back();
    }

    /**
     * Every role, in the shape both screens read.
     *
     * **One count query per role, on purpose.** The holder count has to include deactivated
     * colleagues ({@see User::holdersOf()}), which `withCount('users')` cannot express without
     * a closure that strips a global scope — and a workspace has a handful of roles, so the
     * plain loop is a handful of counts rather than a clever query nobody can read. If a
     * workspace ever has enough roles for this to matter, that is the signal to reach for the
     * closure, not before.
     *
     * The names are **not** translated and must not be: `Administrator` is seeded, but every
     * other role is a word this workspace typed, and there is no locale in which "Stock clerk"
     * becomes something else — the same rule the Users screen's picker follows.
     *
     * @return list<RoleData>
     */
    private static function roles(): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return array_values(
            $roles->map(static fn (Role $role): RoleData => RoleData::fromRole(
                $role,
                User::holdersOf($role)->count(),
            ))->all(),
        );
    }

    /**
     * The validated fields, in the shape {@see SaveRole} declares.
     *
     * `array_filter` on `is_string` rather than a cast: the rules already refuse anything
     * else, so this is the type narrowing PHPStan needs rather than a second check — but a
     * silent `(string)` on a nested array would be the same shape of bug
     * {@see TenantFormRequest::foreignKey()} exists to prevent.
     *
     * @return array{name: string, permissions: list<string>}
     */
    private static function fields(RoleRequest $request): array
    {
        $permissions = $request->validated('permissions');

        return [
            'name' => (string) $request->validated('name'),
            'permissions' => array_values(array_unique(
                is_array($permissions) ? array_filter($permissions, is_string(...)) : [],
            )),
        ];
    }

    /**
     * Refuse to address a role this screen does not own.
     *
     * A role on another guard is a 404 — it is not one of this workspace's, and saying "you
     * may not" about a row somebody cannot see is an answer to a question they did not ask.
     * The built-in Administrator is a 403: it is right there in the list, and the honest
     * answer is that nobody may edit it rather than that it does not exist.
     */
    private static function refuseBuiltIn(Role $role): void
    {
        abort_if($role->guard_name !== 'web', 404);
        abort_if($role->name === TenantRoles::ADMIN, 403);
    }
}
