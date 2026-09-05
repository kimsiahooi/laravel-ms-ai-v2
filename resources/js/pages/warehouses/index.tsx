import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Warehouse as WarehouseIcon } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { ComboboxFilter } from '@/components/data/combobox-filter';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { NewWarehouseButton } from '@/pages/warehouses/_components/new-warehouse-button';
import { WarehouseActions } from '@/pages/warehouses/_components/warehouse-actions';
import { index as locationsIndex } from '@/routes/locations';
import { index } from '@/routes/warehouses';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\WarehouseData — `bun run types:generate`. */
type Warehouse = App.Data.WarehouseData;

type Props = {
    warehouses: Paginated<Warehouse>;
    filters: ResourceFilters;
    /** Every site — the form's picker. Read off the page by the dialog itself. */
    locations: App.Data.OptionData[];
    /** Only the sites that have a warehouse — the filter's options. */
    sitesWithWarehouses: App.Data.OptionData[];
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance. That is also why the row menu reads the
 * site picker off the page rather than taking it as a prop.
 */
const column = columnsFor<Warehouse>();

const columns = column.columns([
    column.accessor('name', {
        header: () => <ColumnHeader label="warehouses.column.name" />,
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {/* The site has no column of its own on a phone, and it is the one
                    thing that distinguishes two warehouses with the same name. */}
                <span className="block text-muted-foreground text-xs md:hidden">
                    {row.original.location}
                </span>
            </>
        ),
    }),
    column.accessor('code', {
        header: () => <ColumnHeader label="warehouses.column.code" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.code ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'sm' },
    }),
    column.accessor('location', {
        header: () => <ColumnHeader label="warehouses.column.site" />,
        cell: ({ row }) => <SiteLink name={row.original.location} />,
        meta: { hideBelow: 'md' },
    }),
    column.accessor('address', {
        header: () => <ColumnHeader label="warehouses.column.address" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* i18n-allow */}
                {row.original.address ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'lg', width: 'max-w-xs truncate' },
    }),
    column.accessor('created_at', {
        header: () => <ColumnHeader label="warehouses.column.created" />,
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
        meta: { hideBelow: 'xl' },
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <WarehouseActions warehouse={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

/**
 * The site cell, as a link to the sites screen searched for this one — the same trade
 * {@see CategoryLink} makes, including falling back to plain text without the
 * destination's view permission.
 */
function SiteLink({ name }: { name: string }) {
    const { t } = useTranslation();
    const { can } = usePermissions();

    if (!can('locations.view')) {
        return <span className="text-muted-foreground">{name}</span>;
    }

    return (
        <Link
            href={locationsIndex(undefined, { query: { search: name } })}
            aria-label={t('warehouses.column.view_site', { name })}
            className="rounded-sm text-link underline underline-offset-4 ring-offset-background transition-colors hover:text-link-hover focus-visible:outline-2 focus-visible:outline-ring focus-visible:outline-offset-2"
        >
            {name}
        </Link>
    );
}

export default function WarehousesIndex({
    warehouses,
    filters,
    locations,
    sitesWithWarehouses,
}: Props) {
    const { t } = useTranslation();

    // One site is not a filter — every warehouse is on it.
    const showSiteFilter = sitesWithWarehouses.length > 1;

    setLayoutProps({
        breadcrumbs: [{ title: t('warehouses.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('warehouses.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('warehouses.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('warehouses.subtitle')}
                    </p>
                </div>
                <NewWarehouseButton />
            </div>

            <DataTable
                href={index().url}
                page={warehouses}
                filters={filters}
                columns={columns}
                getRowId={(warehouse) => String(warehouse.id)}
                only={['warehouses']}
                searchPlaceholder={t('warehouses.search_placeholder')}
                toolbar={
                    showSiteFilter
                        ? (filter) => (
                              <FilterPanel filter={filter}>
                                  <ComboboxFilter
                                      value={filter.values.site ?? ''}
                                      onChange={(site) =>
                                          filter.set('site', site)
                                      }
                                      options={sitesWithWarehouses}
                                      label="warehouses.filter.site"
                                      allLabel="warehouses.filter.all_sites"
                                      manyLabel="warehouses.filter.sites_selected"
                                      searchPlaceholder="warehouses.filter.site_search"
                                      emptyMessage="warehouses.filter.site_empty"
                                      hint="warehouses.filter.site_hint"
                                  />
                              </FilterPanel>
                          )
                        : undefined
                }
                noMatch={{
                    title: t('warehouses.no_match.title'),
                    description: t('warehouses.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Two different nothings. With no sites at all there is nothing to
                    // attach a warehouse to, so the way forward is the sites screen —
                    // offering "New warehouse" here would open a form whose only
                    // required field has no valid answer.
                    locations.length === 0 ? (
                        <EmptyState
                            icon={WarehouseIcon}
                            title={t('warehouses.no_sites.title')}
                            description={t('warehouses.no_sites.description')}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={locationsIndex()}>
                                        {t('warehouses.no_sites.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={WarehouseIcon}
                            title={t('warehouses.empty.title')}
                            description={t('warehouses.empty.description')}
                            action={<NewWarehouseButton />}
                        />
                    )
                }
            />
        </>
    );
}
