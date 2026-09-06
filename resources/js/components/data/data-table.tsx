import { router } from '@inertiajs/react';
import type {
    ColumnDef,
    ColumnOrderState,
    ColumnVisibilityState,
    PaginationState,
    RowData,
    SortingState,
} from '@tanstack/react-table';
import { useTable } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ArrowUpDown, SearchX } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useMemo } from 'react';
import {
    configurableColumns,
    defaultLayout,
    isDefaultLayout,
    toColumnOrder,
    toColumnVisibility,
} from '@/components/data/column-layout';
import { ColumnPanel } from '@/components/data/column-panel';
import { ListToolbar } from '@/components/data/list-toolbar';
import { PaginationBar } from '@/components/data/pagination-bar';
import { columnClasses, features } from '@/components/data/table';
import { useColumnLayout } from '@/components/data/use-column-layout';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { FilterApi, Paginated, ResourceFilters } from '@/types';

/**
 * A server-driven list: search, sort, page size and paging all round-trip to the
 * controller, and the rows handed in are already the page the database returned.
 *
 * The rule that makes this coherent: **`data` is the answer, never the input.**
 * `manualSorting` and `manualPagination` tell TanStack to skip its own sorting and
 * paging stages — they do not fetch and they do not slice. Registering
 * `createSortedRowModel` here would re-order the 25 rows on screen and present it as
 * a sort of the whole table, which is worse than no sorting at all.
 *
 * The component owns every request the list makes. That is deliberate: the rule that
 * a new search must not carry `page` forward lives in one place rather than in each
 * control, which is the mistake that lands someone on an empty page 7 of a fresh
 * result set.
 */
type Props<TRow extends RowData> = {
    /** The index route this list re-requests. */
    href: string;
    /**
     * Which list this is, for remembering its columns.
     *
     * Explicit rather than derived from `href`: column ids are only unique within a
     * screen — `name`, `created_at` and `actions` each appear on several — and an href
     * carries the workspace slug, which would give the same list a different layout in
     * every workspace. Typed against {@see App.Enums.TableKey}, so a typo is a tsc error
     * rather than a preference that silently never loads.
     */
    tableKey: App.Enums.TableKey;
    /** The paginator, straight from the page props. */
    page: Paginated<TRow>;
    /** The server's view of the query — what it searched, sorted and paged by. */
    filters: ResourceFilters;
    // `any` is the library's own signature for a heterogeneous column array —
    // `columnsFor().columns()` builds it with each cell's value type intact.
    columns: Array<ColumnDef<typeof features, TRow, any>>;
    /** Stable row identity. The index would change under sorting and paging. */
    getRowId: (row: TRow) => string;
    searchPlaceholder: string;
    /** Shown when the resource has no rows at all — not when a search found none. */
    emptyState: ReactNode;
    /**
     * Copy for "the search matched nothing". Taken as text rather than a node because
     * the table owns the affordance that fixes it — the clear-search button — and a
     * page that supplied its own could forget it. Falls back to generic wording.
     */
    noMatch?: { title: string; description: string };
    /**
     * Inertia page-prop keys to reload. `filters` is added automatically: leaving it
     * out leaves the echoed search stale, and the toolbar then fights the typing.
     */
    only?: string[];
    /** Extra controls beside the search box — a status filter, a date range. */
    /**
     * This resource's own filter controls, rendered beside the search box.
     *
     * A render prop rather than a node, because a control has to route its change
     * through the table — see {@see FilterApi} — and handing it the setter is what
     * keeps "narrowing starts again at page 1" in one place.
     */
    toolbar?: (filter: FilterApi) => ReactNode;
};

export function DataTable<TRow extends RowData>({
    href,
    tableKey,
    page,
    filters,
    columns,
    getRowId,
    searchPlaceholder,
    emptyState,
    noMatch,
    only,
    toolbar,
}: Props<TRow>) {
    const { t } = useTranslation();

    // Both slices are derived from the server's answer, never mirrored into state.
    // A second copy would lag the props by one round trip, and the table would show a
    // sort arrow the rows do not actually have.
    const pagination = useMemo<PaginationState>(
        () => ({ pageIndex: page.current_page - 1, pageSize: page.per_page }),
        [page.current_page, page.per_page],
    );

    const sorting = useMemo<SortingState>(
        () => [{ id: filters.sort, desc: filters.direction === 'desc' }],
        [filters.sort, filters.direction],
    );

    /**
     * Which columns this reader looks at, and in what order.
     *
     * **The one piece of state this component owns.** Everything above is derived from
     * the server's answer, because everything above is a fact about the query. This is
     * not: it is a fact about the screen, SQL has no opinion on it, and no round trip
     * could answer it better.
     *
     * It is still SSR-safe, and for a slightly different reason than before it was
     * remembered: the seed is now a server prop rather than the column declarations, so
     * both sides still start from the same value. See {@see useColumnLayout}.
     */
    const [layout, setLayout] = useColumnLayout(tableKey, columns);

    const configurable = useMemo(() => configurableColumns(columns), [columns]);

    const columnOrder = useMemo<ColumnOrderState>(
        () => toColumnOrder(layout, columns),
        [layout, columns],
    );

    const columnVisibility = useMemo<ColumnVisibilityState>(
        () => toColumnVisibility(layout),
        [layout],
    );

    const visit = useCallback(
        (
            params: Record<string, string | number | undefined>,
            replace = false,
        ) => {
            router.get(
                href,
                {
                    // An empty term drops out of the URL rather than sitting there blank.
                    search: filters.search || undefined,
                    per_page: filters.per_page,
                    sort: filters.sort,
                    direction: filters.direction,
                    // Before `params`, so a control changing one of them wins.
                    ...filters.extra,
                    ...params,
                },
                {
                    only: only ? [...only, 'filters'] : undefined,
                    preserveState: true,
                    preserveScroll: true,
                    replace,
                },
            );
        },
        [href, filters, only],
    );

    // Anything that changes which rows match starts again at page 1. Note `page` is
    // simply absent from these calls rather than set to 1 — Laravel's default.
    const onSearch = useCallback(
        (search: string) => visit({ search: search || undefined }, true),
        [visit],
    );
    const onPerPage = useCallback(
        (per_page: number) => visit({ per_page }),
        [visit],
    );
    const onPage = useCallback((to: number) => visit({ page: to }), [visit]);

    /**
     * What a per-resource filter control is handed. `set` routes through `visit`, so
     * "narrowing the results starts again at page 1" keeps having exactly one home —
     * a control calling `router.get` itself would be the second copy of that rule.
     */
    const filter = useMemo<FilterApi>(
        () => ({
            values: filters.extra,
            count: Object.keys(filters.extra).length,
            set: (key, value) =>
                visit({ [key]: value || undefined, page: undefined }),
            // One visit, not one per key: clearing three filters should be a single
            // request and a single history entry, not three of each.
            clear: () =>
                visit({
                    ...Object.fromEntries(
                        Object.keys(filters.extra).map((key) => [
                            key,
                            undefined,
                        ]),
                    ),
                    page: undefined,
                }),
        }),
        [filters.extra, visit],
    );

    const table = useTable({
        features,
        columns,
        data: page.data,
        getRowId,
        // `data` IS the page, already ordered by SQL. See the note above.
        manualPagination: true,
        manualSorting: true,
        // The server total, so the table can reason about the last page rather than
        // about the handful of rows it was handed.
        rowCount: page.total,
        autoResetPageIndex: false,
        // The server always resolves a sort, so there is no third "unsorted" state to
        // cycle into — a header toggles between ascending and descending.
        enableSortingRemoval: false,
        sortDescFirst: false,
        state: { pagination, sorting, columnOrder, columnVisibility },
        // Nothing in the app calls the table's own visibility/order setters — the panel
        // drives `layout` directly. These are wired anyway so that `column.toggleVisibility()`
        // is not a silent no-op for whoever reaches for it next. They take the current
        // `layout` rather than an updater because the setter also schedules a save, and
        // a state updater has to stay pure.
        onColumnVisibilityChange: (updater) => {
            const next =
                typeof updater === 'function'
                    ? updater(columnVisibility)
                    : updater;

            setLayout({
                ...layout,
                hidden: layout.order.filter((id) => next[id] === false),
            });
        },
        onColumnOrderChange: (updater) => {
            const next =
                typeof updater === 'function' ? updater(columnOrder) : updater;

            setLayout({
                ...layout,
                order: next.filter((id) => layout.order.includes(id)),
            });
        },
        onSortingChange: (updater) => {
            // TanStack calls this with the functional form from its own handlers.
            const next =
                typeof updater === 'function' ? updater(sorting) : updater;
            const column = next[0];

            visit({
                sort: column?.id ?? filters.sort,
                direction: column?.desc ? 'desc' : 'asc',
                page: undefined,
            });
        },
        onPaginationChange: (updater) => {
            const next =
                typeof updater === 'function' ? updater(pagination) : updater;

            visit({
                page: next.pageIndex + 1,
                per_page: next.pageSize,
            });
        },
    });

    const rows = table.getRowModel().rows;
    const searching = filters.search !== '';
    // Narrowed by anything at all. The distinction matters twice below: a list showing
    // nothing because of a filter is not an empty list, and hiding the toolbar in that
    // state would take away the only control that can undo it.
    const narrowed = searching || filter.count > 0;

    return (
        <Card className="gap-0 overflow-hidden py-0">
            {/* Nothing to search when nothing exists — the box would only be furniture. */}
            {(page.total > 0 || narrowed) && (
                <ListToolbar
                    search={filters.search}
                    placeholder={searchPlaceholder}
                    onSearch={onSearch}
                    extra={toolbar?.(filter)}
                    columns={
                        // One configurable column is not a choice — there is nothing to
                        // reorder and hiding it would leave the list blank.
                        configurable.length > 1 ? (
                            <ColumnPanel
                                columns={configurable}
                                layout={layout}
                                onChange={setLayout}
                                onReset={() =>
                                    setLayout(defaultLayout(columns))
                                }
                                canReset={!isDefaultLayout(layout, columns)}
                                sortedBy={filters.sort}
                            />
                        ) : undefined
                    }
                />
            )}

            {rows.length > 0 ? (
                // `Table` brings its own `overflow-x-auto` wrapper, so a wide table
                // scrolls inside the card and the page body never does.
                <Table>
                    <TableHeader className="bg-muted/40">
                        {table.getHeaderGroups().map((group) => (
                            <TableRow
                                key={group.id}
                                className="hover:bg-transparent"
                            >
                                {group.headers.map((header) => {
                                    const meta = header.column.columnDef.meta;
                                    const sortable = filters.sortable.includes(
                                        header.column.id,
                                    );
                                    const active =
                                        sortable &&
                                        filters.sort === header.column.id;

                                    return (
                                        <TableHead
                                            key={header.id}
                                            colSpan={header.colSpan}
                                            aria-sort={
                                                active
                                                    ? filters.direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : undefined
                                            }
                                            className={cn(
                                                'h-11 font-medium text-muted-foreground text-xs first:pl-4 last:pr-4',
                                                columnClasses(meta),
                                            )}
                                        >
                                            {header.isPlaceholder ? null : sortable ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    className={cn(
                                                        'group -mx-2 inline-flex items-center gap-1 rounded-sm px-2 py-1 hover:text-foreground focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                                        meta?.align === 'end' &&
                                                            'flex-row-reverse',
                                                        active &&
                                                            'text-foreground',
                                                    )}
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                    <SortIcon
                                                        direction={
                                                            active
                                                                ? filters.direction
                                                                : null
                                                        }
                                                    />
                                                </button>
                                            ) : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>

                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                {/*
                                    getVisibleCells, not getAllCells: the header row
                                    respects visibility and order the moment the features
                                    are registered, and the body does not. Mismatched,
                                    every cell sits under the wrong heading.
                                */}
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className={cn(
                                            'py-3 first:pl-4 last:pr-4',
                                            columnClasses(
                                                cell.column.columnDef.meta,
                                            ),
                                        )}
                                    >
                                        <table.FlexRender cell={cell} />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            ) : narrowed ? (
                <div className="px-4 py-12">
                    <EmptyState
                        icon={SearchX}
                        title={noMatch?.title ?? t('common.list.no_matches')}
                        description={
                            // The search wording names the term, which is only
                            // meaningful when there is one. A list narrowed by a filter
                            // alone gets its own sentence rather than "nothing matches
                            // ''".
                            searching
                                ? (noMatch?.description ??
                                  t('common.list.no_matches_hint', {
                                      search: filters.search,
                                  }))
                                : t('common.list.no_matches_filtered')
                        }
                        action={
                            // One button per thing that can be undone, rather than one
                            // compound label that has to describe every combination.
                            <>
                                {searching && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onSearch('')}
                                    >
                                        {t('common.actions.clear_search')}
                                    </Button>
                                )}
                                {filter.count > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={filter.clear}
                                    >
                                        {t('common.filter.clear_all')}
                                    </Button>
                                )}
                            </>
                        }
                    />
                </div>
            ) : page.total > 0 ? (
                // A real state, not a theoretical one: delete the last row of the last
                // page and the redirect carries `?page=N` to a page that no longer
                // exists. v1 showed "no results match ''" here, quoting a search
                // nobody made.
                <div className="px-4 py-12">
                    <EmptyState
                        icon={SearchX}
                        title={t('common.list.page_empty')}
                        description={t('common.list.page_empty_hint')}
                        action={
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onPage(1)}
                            >
                                {t('common.list.back_to_first')}
                            </Button>
                        }
                    />
                </div>
            ) : (
                <div className="px-4 py-12">{emptyState}</div>
            )}

            {page.total > 0 && (
                <PaginationBar
                    page={page}
                    onPage={onPage}
                    onPerPage={onPerPage}
                />
            )}
        </Card>
    );
}

/**
 * The sort arrow. An inactive column still renders one, dimmed, so a header does not
 * change width the moment it is clicked.
 */
function SortIcon({ direction }: { direction: 'asc' | 'desc' | null }) {
    if (direction === 'asc') {
        return <ArrowUp className="size-3.5" />;
    }

    if (direction === 'desc') {
        return <ArrowDown className="size-3.5" />;
    }

    return (
        <ArrowUpDown className="size-3.5 opacity-40 transition-opacity group-hover:opacity-100" />
    );
}
