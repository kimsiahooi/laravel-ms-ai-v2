<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Every list that remembers its columns.
 *
 * A closed set on purpose. Column ids are only unique *within* a screen — `name`,
 * `created_at` and `actions` each appear on several — so a stored layout has to be keyed
 * by the list it belongs to, and an open-ended key would let a signed-in user grow their
 * `table_columns` column without limit.
 *
 * Deliberately *not* derived from the route or the URL. Those carry the workspace slug,
 * which would give the same list a different layout in every workspace; the columns are
 * identical because the code is, so the preference should be too.
 *
 * `#[TypeScript]` emits `App.Enums.TableKey`, which types `DataTable`'s `tableKey` prop —
 * so a typo on a page is a tsc error rather than a preference that silently never loads.
 * The server validates against this same list with `Rule::enum`.
 */
#[TypeScript]
enum TableKey: string
{
    case AdminTenants = 'admin-tenants';
    case AdminTenantsTrashed = 'admin-tenants-trashed';
    case Categories = 'categories';
    case Customers = 'customers';
    case Locations = 'locations';
    case Products = 'products';
    case RawMaterials = 'raw-materials';
    case StockMovements = 'stock-movements';
    case StockTransfers = 'stock-transfers';
    case Suppliers = 'suppliers';
    case Warehouses = 'warehouses';
}
