<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\PermissionGroupData;
use App\Data\PermissionOptionData;
use App\Enums\PermissionAction;
use App\Enums\PermissionScreen;
use App\Http\Controllers\Tenant\MediaController;
use App\Http\Middleware\AuthorizeTenantRoute;

/**
 * The tenant permission catalog — the permission names to seed, the matrix the role editor
 * renders, and the route → permission map {@see AuthorizeTenantRoute} enforces.
 *
 * **What a screen is, and what may be done to it, now lives on {@see PermissionScreen}.** This
 * class is the three things built out of that: names, matrix, map. Moving the catalog onto the
 * enum is what lets the browser receive a typed value and compose its own translation key, so
 * a screen added without its three translations fails `tsc` — the only mechanism available,
 * because `check:i18n` reads `resources/js` and cannot see a label that arrives from PHP as a
 * prop.
 *
 * Adding a screen or an action flows everywhere from one place: the seeded permission, the
 * role editor, and route gating.
 */
final class TenantPermissions
{
    /**
     * Lifecycle and custom routes that are not plain resource CRUD → the permission that
     * governs them. Lifecycle actions map to the resource's edit permission, or its create
     * permission for screens that have no edit.
     *
     * A list of permissions rather than one means **any of them will do**, for a route several
     * screens share — see `stock.on-hand`.
     *
     * **Form pages are no longer listed here.** `{screen}.create` and `{screen}.edit` are
     * auto-mapped by {@see routeMap()}, which is the structural fix for a trap that had already
     * cost two security fixes; an override per page only worked when somebody remembered.
     *
     * @var array<string, string|list<string>>
     */
    private const ROUTE_OVERRIDES = [
        // The on-hand lookup returns a stock level, so it is gated like the screens that
        // ask for it rather than left open to any signed-in user. **Any of them will do**:
        // it answers the same question for each, and gating it on one screen's permission
        // would 403 somebody who may read a different one. Add a screen here when it
        // starts calling the lookup.
        'stock.on-hand' => ['stock-movements.view', 'stock-transfers.view', 'stock-takes.view'],
        'products.bom' => 'products.update',
        'warehouses.reorder-levels.update' => 'warehouses.update',
        'stock-takes.post' => 'stock-takes.create',
        'stock-takes.cancel' => 'stock-takes.create',
        // Filling a count sheet in — one saved number, one item found on the shelf. Both
        // map to `stock-takes.create` because that is the permission to *take* a count,
        // and the screen has no separate notion of editing one.
        'stock-takes.count' => 'stock-takes.create',
        'stock-takes.lines' => 'stock-takes.create',
        'purchase-orders.receive' => 'purchase-orders.update',
        'purchase-orders.cancel' => 'purchase-orders.update',
        'purchase-returns.complete' => 'purchase-returns.update',
        'purchase-returns.cancel' => 'purchase-returns.update',
        'sales-orders.fulfill' => 'sales-orders.update',
        'sales-orders.cancel' => 'sales-orders.update',
        // Downloading the e-invoice reads the order's data — gate it on view.
        'sales-orders.e-invoice' => 'sales-orders.view',
        'sales-returns.complete' => 'sales-returns.update',
        'sales-returns.cancel' => 'sales-returns.update',
        // Reactivating somebody is an edit to who may sign in, not a separate power.
        'users.restore' => 'users.update',
    ];

    /**
     * Every permission name to seed.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (PermissionScreen::cases() as $screen) {
            foreach ($screen->actions() as $action) {
                $names[] = self::name($screen, $action);
            }
        }

        return $names;
    }

    /**
     * The catalog grouped by screen, for the role editor.
     *
     * **No words cross the wire.** Each group carries its screen as an enum case and each
     * option its action, and the browser composes `permissions.screen.{value}` and
     * `permissions.action.{value}` — the same shape the ledger's source cell and the units
     * column already use. This method used to build two English strings per checkbox, one of
     * them by concatenation; see {@see PermissionOptionData} for why that was wrong in three
     * separate ways.
     *
     * @return list<PermissionGroupData>
     */
    public static function matrix(): array
    {
        return array_map(
            static fn (PermissionScreen $screen): PermissionGroupData => new PermissionGroupData(
                screen: $screen,
                permissions: array_map(
                    static fn (PermissionAction $action): PermissionOptionData => new PermissionOptionData(
                        name: self::name($screen, $action),
                        action: $action,
                    ),
                    $screen->actions(),
                ),
            ),
            PermissionScreen::cases(),
        );
    }

    /**
     * Route name → the permission it requires.
     *
     * Unmapped routes (dashboard, personal settings, logout) are open to any signed-in tenant
     * user, which is deliberate and is also the sharpest edge in this codebase: a route this
     * map cannot find is a route nothing guards. It has cost two security fixes — the purchase
     * and sales order form pages, both of which render an entire catalog and both of which
     * were readable by anybody with a login.
     *
     * **So the form pages are auto-mapped now, conditionally.** `{screen}.create` needs the
     * create permission and `{screen}.edit` the update permission — but only where the screen
     * declares that action. The condition is load-bearing rather than tidy: emitting
     * `settings.create` unconditionally would name a permission that is in no role at all, so
     * any future route by that name would 403 every user including an Administrator.
     *
     * `media` is unmapped but not open: one route serves the files of every kind of record, so
     * the permission depends on the row rather than the route, and {@see MediaController} reads
     * it off the owner.
     *
     * @return array<string, string|list<string>>
     */
    public static function routeMap(): array
    {
        $map = [];

        foreach (PermissionScreen::cases() as $screen) {
            foreach ($screen->actions() as $action) {
                $map[$screen->value.'.'.$action->routeSuffix()] = self::name($screen, $action);
            }

            // Every screen has a view permission; a show route (where one exists) shares it.
            $map[$screen->value.'.show'] = self::name($screen, PermissionAction::View);

            if ($screen->allows(PermissionAction::Create)) {
                $map[$screen->value.'.create'] = self::name($screen, PermissionAction::Create);
            }

            if ($screen->allows(PermissionAction::Update)) {
                $map[$screen->value.'.edit'] = self::name($screen, PermissionAction::Update);
            }
        }

        return [...$map, ...self::ROUTE_OVERRIDES];
    }

    /** `categories.view` — the one place the two halves are joined. */
    private static function name(PermissionScreen $screen, PermissionAction $action): string
    {
        return $screen->value.'.'.$action->value;
    }
}
