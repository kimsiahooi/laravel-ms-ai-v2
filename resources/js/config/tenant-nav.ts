import {
    ArrowLeftRight,
    ArrowRightLeft,
    Boxes,
    Building,
    ClipboardList,
    LayoutGrid,
    MapPin,
    Package,
    Receipt,
    Settings2,
    ShieldCheck,
    ShoppingCart,
    Tags,
    Truck,
    Users as UsersIcon,
    Warehouse,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as categories } from '@/routes/categories';
import { index as customers } from '@/routes/customers';
import { index as locations } from '@/routes/locations';
import { index as products } from '@/routes/products';
import { index as purchaseOrders } from '@/routes/purchase-orders';
import { index as rawMaterials } from '@/routes/raw-materials';
import { index as roles } from '@/routes/roles';
import { index as salesOrders } from '@/routes/sales-orders';
import { index as businessSettings } from '@/routes/settings';
import { index as stockMovements } from '@/routes/stock-movements';
import { index as stockTakes } from '@/routes/stock-takes';
import { index as stockTransfers } from '@/routes/stock-transfers';
import { index as suppliers } from '@/routes/suppliers';
import { index as users } from '@/routes/users';
import { index as warehouses } from '@/routes/warehouses';
import type { TenantNavGroup } from '@/types/navigation';

/**
 * The workspace sidebar, in one place.
 *
 * Both the sidebar and the ⌘K palette read this, so the two cannot drift into
 * disagreeing about what exists.
 *
 * **It is a function, and the hrefs are resolved inside it.** Not a style choice: the
 * tenant slug is registered as a URL default per render (see app.tsx), while module
 * scope runs once when the bundle loads with no tenant in sight. A route helper called
 * out there emits a literal `/$tenant/categories`, which is a hydration mismatch
 * against the browser's `/demo/categories` — invisible until a hard page load.
 *
 * Titles are `TranslationKey` for the same class of reason: there is no locale at
 * module scope either, and the layout resolves them during render.
 *
 * Each module adds its own line as it lands. Groups arrive with their first member
 * rather than up front — an empty group renders as a heading with nothing under it.
 * Still to come: the rest of Orders, then Insights, Team.
 */
export function tenantNavGroups(
    can?: (permission: string) => boolean,
): TenantNavGroup[] {
    const groups: TenantNavGroup[] = [
        {
            items: [
                {
                    title: 'tenant.nav.dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: 'tenant.nav.catalog',
            items: [
                {
                    // The module names itself; the group heading is the shell's word
                    // for a run of modules, so only that one lives in tenant.php.
                    title: 'categories.title',
                    href: categories(),
                    icon: Tags,
                    permission: 'categories.view',
                },
                {
                    title: 'suppliers.title',
                    href: suppliers(),
                    icon: Truck,
                    permission: 'suppliers.view',
                },
                {
                    title: 'customers.title',
                    href: customers(),
                    icon: Building,
                    permission: 'customers.view',
                },
                {
                    title: 'raw-materials.title',
                    href: rawMaterials(),
                    icon: Boxes,
                    permission: 'raw-materials.view',
                },
                {
                    title: 'products.title',
                    href: products(),
                    icon: Package,
                    permission: 'products.view',
                },
            ],
        },
        {
            label: 'tenant.nav.stock',
            items: [
                {
                    title: 'locations.title',
                    href: locations(),
                    icon: MapPin,
                    permission: 'locations.view',
                },
                {
                    title: 'warehouses.title',
                    href: warehouses(),
                    icon: Warehouse,
                    permission: 'warehouses.view',
                },
                {
                    title: 'stock-movements.title',
                    href: stockMovements(),
                    icon: ArrowLeftRight,
                    permission: 'stock-movements.view',
                },
                {
                    title: 'stock-transfers.title',
                    href: stockTransfers(),
                    icon: ArrowRightLeft,
                    permission: 'stock-transfers.view',
                },
                {
                    title: 'stock-takes.title',
                    href: stockTakes(),
                    icon: ClipboardList,
                    permission: 'stock-takes.view',
                },
            ],
        },
        {
            // "Orders", plural because the returns are still to come. Purchases sit
            // above sales deliberately: a workspace buys before it has anything to sell,
            // and that is the order the two screens are learned in.
            label: 'tenant.nav.orders',
            items: [
                {
                    title: 'purchase-orders.title',
                    href: purchaseOrders(),
                    icon: ShoppingCart,
                    permission: 'purchase-orders.view',
                },
                {
                    title: 'sales-orders.title',
                    href: salesOrders(),
                    icon: Receipt,
                    permission: 'sales-orders.view',
                },
            ],
        },
        {
            // The heading this group's own comment promised: it sat unlabelled while
            // business settings was its only entry, because "Workspace" over a single
            // line is furniture, and Users is the second workspace-wide screen that
            // was being waited for.
            //
            // Filed here rather than in the account-settings sidebar because both
            // belong to the business rather than to the reader: everyone sees the same
            // ones, and only a role holding the permission sees them at all. Account
            // settings — profile, password, appearance — are the other sidebar.
            label: 'tenant.nav.workspace',
            items: [
                {
                    title: 'users.title',
                    href: users(),
                    icon: UsersIcon,
                    permission: 'users.view',
                },
                {
                    // Directly under Users, because the two are one question read from
                    // either end: who is here, and what each of them can reach.
                    title: 'roles.title',
                    href: roles(),
                    icon: ShieldCheck,
                    permission: 'roles.view',
                },
                {
                    title: 'business-settings.title',
                    href: businessSettings(),
                    icon: Settings2,
                    permission: 'settings.view',
                },
            ],
        },
    ];

    // No `can` means "do not filter" — the palette and the sidebar both pass one, but
    // anything wanting the full list (a role editor, say) can ask for it unfiltered.
    if (!can) {
        return groups;
    }

    return (
        groups
            .map((group) => ({
                ...group,
                items: group.items.filter(
                    (item) => !item.permission || can(item.permission),
                ),
            }))
            // A group whose every entry was filtered out would otherwise render as a
            // heading with nothing beneath it.
            .filter((group) => group.items.length > 0)
    );
}
