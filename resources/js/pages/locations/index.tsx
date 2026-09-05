import { Head, setLayoutProps } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { LocationActions } from '@/pages/locations/_components/location-actions';
import { NewLocationButton } from '@/pages/locations/_components/new-location-button';
import { index } from '@/routes/locations';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\LocationData — `bun run types:generate`. */
type Location = App.Data.LocationData;

type Props = {
    locations: Paginated<Location>;
    filters: ResourceFilters;
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * A column's id is the column the server would sort by. Which of them are actually
 * clickable is decided by the controller's allow-list, which arrives in
 * `filters.sortable`; nothing here declares it. `address` and `creator` are not on
 * that list, so their headers render plain.
 */
const column = columnsFor<Location>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('locations.column.name'),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {/* The code has no column of its own on a phone, so it rides along
                    under the name rather than being lost entirely. */}
                {row.original.code && (
                    <span className="block text-muted-foreground text-xs sm:hidden">
                        {row.original.code}
                    </span>
                )}
            </>
        ),
    }),
    column.accessor('code', {
        ...heading('locations.column.code', { hideBelow: 'sm' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.code ?? '—'}
            </span>
        ),
    }),
    column.accessor('address', {
        ...heading('locations.column.address', {
            hideBelow: 'md',
            width: 'max-w-xs truncate',
        }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* i18n-allow */}
                {row.original.address ?? '—'}
            </span>
        ),
    }),
    column.accessor('created_at', {
        ...heading('locations.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.accessor('creator', {
        ...heading('locations.column.creator', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* Null for a seeded row, or once the author has been removed. i18n-allow */}
                {row.original.creator ?? '—'}
            </span>
        ),
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <LocationActions location={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

export default function LocationsIndex({ locations, filters }: Props) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `LocationsIndex.layout`: a breadcrumb title
    // is a resolved string, and resolving one needs t(), which cannot run at module
    // scope. TenantLayout supplies the workspace crumb ahead of this one.
    setLayoutProps({
        breadcrumbs: [{ title: t('locations.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('locations.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('locations.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('locations.subtitle')}
                    </p>
                </div>
                <NewLocationButton />
            </div>

            <DataTable
                href={index().url}
                page={locations}
                filters={filters}
                columns={columns}
                getRowId={(location) => String(location.id)}
                only={['locations']}
                searchPlaceholder={t('locations.search_placeholder')}
                noMatch={{
                    title: t('locations.no_match.title'),
                    description: t('locations.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={MapPin}
                        title={t('locations.empty.title')}
                        description={t('locations.empty.description')}
                        action={<NewLocationButton />}
                    />
                }
            />
        </>
    );
}
