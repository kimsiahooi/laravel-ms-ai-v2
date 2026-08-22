import { LayoutGrid } from 'lucide-react';
import { dashboard } from '@/routes';
import type { TenantNavGroup } from '@/types/navigation';

/**
 * The workspace sidebar, in one place.
 *
 * Both the sidebar and the ⌘K palette read this, so the two cannot drift into
 * disagreeing about what exists.
 *
 * **It is one entry today, and that is the honest size of it.** The other twenty
 * modules have no routes yet, so there is nothing to link to — a Wayfinder helper for
 * a route that does not exist is not a broken link, it is a compile error. Each module
 * adds its own line here as it lands, which is the maintenance cost of keeping the list
 * typed: titles are `TranslationKey`, icons are real components, and hrefs are route
 * helpers, so a typo in any of the three fails `tsc` rather than rendering a dead link.
 *
 * Groups arrive with their modules too — Catalog, Stock, Orders, Insights, Team.
 * Adding empty ones now would render as headings with nothing under them.
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
