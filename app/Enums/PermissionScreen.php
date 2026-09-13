<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\TenantPermissions;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Every screen a role can be granted access to, and what can be done to each.
 *
 * The value is the first half of every permission name — `categories.view` — so these strings
 * are seeded data in every tenant database and cannot be renamed without a migration.
 *
 * **The catalog is complete ahead of the screens it names.** Several of these modules are
 * still to be migrated, and a permission with no route yet is inert: nothing can request it.
 * Declaring the whole set once is what stops every later module from needing a re-seed across
 * every existing tenant — the argument {@see TenantPermissions} has always made, now stated
 * where the set actually lives.
 *
 * **Production orders are deliberately absent.** They were declared here once and removed when
 * the module was ruled out of scope — an inventory system does not manufacture. The three
 * permission rows survive in tenant databases that were seeded before the removal, because the
 * seeder is additive and never revokes; they are harmless, because no route can ask for them.
 *
 * **The words a person reads are not here.** They live in `lang/{locale}/permissions.php` keyed
 * by these values, and the role editor composes `permissions.screen.{value}`. `#[TypeScript]`
 * emits the union, which is what makes that template-literal key type-check — so a screen
 * added here without its three translations is a `tsc` error, and `check:i18n` cannot see into
 * PHP to tell you otherwise.
 */
#[TypeScript]
enum PermissionScreen: string
{
    case Categories = 'categories';
    case Suppliers = 'suppliers';
    case Customers = 'customers';
    case RawMaterials = 'raw-materials';
    case Products = 'products';
    case Locations = 'locations';
    case Warehouses = 'warehouses';
    case StockMovements = 'stock-movements';
    case StockTransfers = 'stock-transfers';
    case StockTakes = 'stock-takes';
    case PurchaseOrders = 'purchase-orders';
    case PurchaseReturns = 'purchase-returns';
    case SalesOrders = 'sales-orders';
    case SalesReturns = 'sales-returns';
    case Reports = 'reports';
    case Activity = 'activity';
    case Users = 'users';
    case Roles = 'roles';
    case Settings = 'settings';

    /**
     * What may be done to this screen.
     *
     * The default is the full four, because most screens are ordinary CRUD; the match names
     * only the screens that are not, which is the set worth reading. Two shapes recur:
     *
     * - **Append-only** — a ledger row or a transfer is a record of something that happened,
     *   so it can be written and never amended. Stock takes add delete, because a count sheet
     *   started by mistake is not a record of anything.
     * - **Read-only** — reports and the activity log are views over other modules' data.
     *
     * Settings is its own shape: one row that exists already, so it is viewed and changed but
     * never created or destroyed. That is exactly why {@see TenantPermissions::routeMap()}
     * emits the `create` and `edit` page mappings *conditionally* — an unconditional
     * `settings.create` would name a permission no role holds, and any future route by that
     * name would then 403 everybody, Administrators included.
     *
     * @return list<PermissionAction>
     */
    public function actions(): array
    {
        return match ($this) {
            self::StockMovements, self::StockTransfers => [
                PermissionAction::View,
                PermissionAction::Create,
            ],
            self::StockTakes => [
                PermissionAction::View,
                PermissionAction::Create,
                PermissionAction::Delete,
            ],
            self::Reports, self::Activity => [PermissionAction::View],
            self::Settings => [PermissionAction::View, PermissionAction::Update],
            default => PermissionAction::cases(),
        };
    }

    /** Whether this screen grants `$action` at all. */
    public function allows(PermissionAction $action): bool
    {
        return in_array($action, $this->actions(), true);
    }
}
