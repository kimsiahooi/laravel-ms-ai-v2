import { Link } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { index as categories } from '@/routes/categories';
import { index as suppliers } from '@/routes/suppliers';
import type { TranslationKey } from '@/types/lang';

/**
 * The category and supplier cells, as links to the screen each belongs to.
 *
 * They link to that screen **searched for this row**, not to a detail page: neither
 * module has one — each is a single list with a dialog over it, and no `show` route
 * exists to link to. Searching is the app's own way of finding one row, so the link
 * says "show me this category over there" in the vocabulary the screen already has.
 *
 * A name that has since been deleted lands on that screen's "No categories match"
 * state, naming the term. That is not a dead end — it is the answer to why the link
 * was followed.
 *
 * Both fall back to plain, unlinked text without the destination's view permission.
 * A link that 403s is worse than no link, and AuthorizeTenantRoute would refuse it.
 *
 * The `undefined` first argument to each route helper is the route's own parameters —
 * only `{tenant}`, which SetTenantUrlDefault fills in through URL::defaults. The query
 * is the second argument, not the first.
 */
export function CategoryLink({ name }: { name: string | null }) {
    return (
        <FilingLink
            name={name}
            permission="categories.view"
            href={(search) => categories(undefined, { query: { search } })}
            label="products.column.view_category"
        />
    );
}

export function SupplierLink({ name }: { name: string | null }) {
    return (
        <FilingLink
            name={name}
            permission="suppliers.view"
            href={(search) => suppliers(undefined, { query: { search } })}
            label="products.column.view_supplier"
        />
    );
}

/**
 * One row's link to the screen its value lives on.
 *
 * **Styled as a link, not as a subtle affordance.** These were a badge and muted text,
 * which looked like the data they are and gave nobody a reason to click. Underlined and
 * in the primary colour is the one convention every reader already knows, and it is the
 * `text-link` token rather than `text-primary` because the primary is a *surface* colour
 * — it is contrast-checked against `primary-foreground` sitting on top of it, not
 * against the page behind it.
 */
function FilingLink({
    name,
    permission,
    href,
    label,
}: {
    name: string | null;
    permission: string;
    href: (search: string) => ReturnType<typeof categories>;
    label: TranslationKey;
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (name === null) {
        // A dash, not a word: nothing here to translate. i18n-allow
        return <span className="text-muted-foreground">—</span>;
    }

    if (!can(permission)) {
        return <span className="text-muted-foreground">{name}</span>;
    }

    return (
        <Link
            href={href(name)}
            aria-label={t(label, { name })}
            className="rounded-sm text-link underline underline-offset-4 ring-offset-background transition-colors hover:text-link-hover focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2"
        >
            {name}
        </Link>
    );
}
