import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { TranslationKey } from '@/types/lang';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/**
 * One entry in the workspace sidebar.
 *
 * Deliberately not `NavItem`: the title is a translation *key*, not a sentence. The
 * nav is built once at module scope where there is no locale, so a resolved string
 * there would freeze whichever language rendered first — under SSR, one process
 * serves every workspace.
 *
 * `permission` names what the entry needs. Absent means everyone signed in may see it.
 * It hides a link; it never grants one — AuthorizeTenantRoute is the boundary, and a
 * hidden entry is still reachable by typing the URL until the server refuses it.
 */
export type TenantNavItem = {
    title: TranslationKey;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    permission?: string;
};

/** A labelled run of sidebar entries. An unlabelled group renders without a heading. */
export type TenantNavGroup = {
    label?: TranslationKey;
    items: TenantNavItem[];
};
