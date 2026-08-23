import { Boxes, Building, LayoutGrid, Tags, Truck } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as categories } from '@/routes/categories';
import { index as customers } from '@/routes/customers';
import { index as rawMaterials } from '@/routes/raw-materials';
import { index as suppliers } from '@/routes/suppliers';
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
 * Still to come: Stock, Orders, Insights, Team.
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
