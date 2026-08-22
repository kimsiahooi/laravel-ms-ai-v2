import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

/**
 * A Laravel LengthAwarePaginator as Inertia serializes it. `links` is the raw
 * page-link payload; the UI builds its own window from `current_page`/`last_page`,
 * so most components only need the counts.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

/**
 * The query state a server-paginated list round-trips. Every list controller sends
 * exactly this under `filters`, and the table echoes it back on every request.
 *
 * `sortable` is sent by the server rather than declared in the page, because it is the
 * same list that guards `ORDER BY` against injection — see SortsResourceQuery. A
 * second copy in the client could disagree with it, and the disagreement would show up
 * as a header that looks clickable and silently does nothing.
 */
export type ResourceFilters = {
    search: string;
    per_page: number;
    sort: string;
    direction: 'asc' | 'desc';
    sortable: string[];
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
