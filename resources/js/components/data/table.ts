import type { RowData } from '@tanstack/react-table';
import {
    createColumnHelper,
    metaHelper,
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures,
} from '@tanstack/react-table';

/**
 * The table vocabulary every list in the app shares: which TanStack features are
 * registered, and what a column may say about itself beyond its cell.
 *
 * v9 registers nothing by default — `table.setPageSize` and
 * `column.getToggleSortingHandler` do not exist until their feature is listed here.
 * Note what is *absent*: `createSortedRowModel` and `createPaginatedRowModel`, the
 * client-side processing stages. Sorting and paging happen in SQL, so `data` is
 * already the right page in the right order; registering those models would re-sort
 * the ~25 rows on screen and call it a global sort.
 */
export const features = tableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
    columnMeta: metaHelper<ColumnMeta>(),
});

/** Which breakpoint a column appears at. Below it, the column is not rendered. */
export type Breakpoint = 'sm' | 'md' | 'lg' | 'xl';

/**
 * Presentation a column declares about itself, rather than each cell repeating the
 * same Tailwind by hand.
 *
 * This exists because v1 put free-text classes in `meta.className` and the app drifted:
 * two columns with identical intent ended up hiding at different breakpoints, and
 * nothing could tell which was right. Naming the intent makes the answer checkable.
 */
export type ColumnMeta = {
    /** Hide below this breakpoint. A phone should not scroll to read the first column. */
    hideBelow?: Breakpoint;
    /** Right-align — money, quantities, counts, and anything read by its last digit. */
    align?: 'end';
    /** Lining figures, so digits stack in a column instead of wandering. */
    numeric?: boolean;
    /** A width utility for a column that should not stretch, e.g. `w-12` for actions. */
    width?: string;
};

/**
 * Column definitions for a row type.
 *
 *     const column = columnsFor<Workspace>();
 *     const columns = column.columns([
 *         column.accessor('name', { header: () => …, cell: … }),
 *     ]);
 *
 * **A column's id is the column the server sorts by.** `accessor('name', …)` is
 * sortable as `name` if — and only if — the controller's allow-list contains it; the
 * table reads that list from `filters.sortable` and makes exactly those headers
 * clickable. Nothing in the page decides it.
 */
export function columnsFor<TRow extends RowData>() {
    return createColumnHelper<typeof features, TRow>();
}

/** Tailwind cannot see a class it has to build at runtime, so the map is spelled out. */
const HIDE_BELOW: Record<Breakpoint, string> = {
    sm: 'hidden sm:table-cell',
    md: 'hidden md:table-cell',
    lg: 'hidden lg:table-cell',
    xl: 'hidden xl:table-cell',
};

/** The classes a column's meta asks for, for both its header and its cells. */
export function columnClasses(meta: ColumnMeta | undefined): string {
    if (!meta) {
        return '';
    }

    return [
        meta.hideBelow ? HIDE_BELOW[meta.hideBelow] : '',
        meta.align === 'end' ? 'text-right' : '',
        meta.numeric ? 'tabular-nums' : '',
        meta.width ?? '',
    ]
        .filter(Boolean)
        .join(' ');
}
