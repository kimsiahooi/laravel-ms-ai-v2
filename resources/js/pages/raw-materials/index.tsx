import { Head, setLayoutProps } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { NewRawMaterialButton } from '@/pages/raw-materials/_components/new-raw-material-button';
import { RawMaterialActions } from '@/pages/raw-materials/_components/raw-material-actions';
import { index } from '@/routes/raw-materials';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\RawMaterialData — `bun run types:generate`. */
type RawMaterial = App.Data.RawMaterialData;

type Props = {
    rawMaterials: Paginated<RawMaterial>;
    filters: ResourceFilters;
    /** Unit codes in use, for the filter. See the controller's unitsInUse(). */
    unitsInUse: App.Enums.Unit[];
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * The unit rides under the name rather than taking a column of its own. It is short,
 * it is never sorted by, and reading "Steel rod 12mm · kg" as one line is how someone
 * actually thinks about the material — the alternative spends a whole column on two
 * characters.
 */
const column = columnsFor<RawMaterial>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('raw-materials.column.name', { width: 'max-w-[20rem]' }),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                <UnitSymbol unit={row.original.unit} />
            </>
        ),
    }),
    column.accessor('sku', {
        ...heading('raw-materials.column.sku', { hideBelow: 'sm' }),
        // Monospaced, because a SKU is read character by character when it is being
        // compared against a delivery note.
        cell: ({ row }) => (
            <span className="font-mono text-muted-foreground text-xs">
                {row.original.sku}
            </span>
        ),
    }),
    column.accessor('created_at', {
        ...heading('raw-materials.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.accessor('creator', {
        ...heading('raw-materials.column.creator', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.creator ?? '—'}
            </span>
        ),
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <RawMaterialActions rawMaterial={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

/**
 * The material's unit under its name. The short form — `kg`, not `Kilogram (kg)` — since
 * it sits inside a table cell, where anything longer is noise.
 */
function UnitSymbol({ unit }: { unit: App.Enums.Unit }) {
    const { t } = useTranslation();

    return (
        <span className="block text-muted-foreground text-xs">
            {t(`units.symbol.${unit}` as const)}
        </span>
    );
}

export default function RawMaterialsIndex({
    rawMaterials,
    filters,
    unitsInUse,
}: Props) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `.layout`: a breadcrumb title is a resolved
    // string, and resolving one needs t(), which cannot run at module scope.
    setLayoutProps({
        breadcrumbs: [{ title: t('raw-materials.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('raw-materials.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('raw-materials.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('raw-materials.subtitle')}
                    </p>
                </div>
                <NewRawMaterialButton />
            </div>

            <DataTable
                href={index().url}
                page={rawMaterials}
                filters={filters}
                columns={columns}
                getRowId={(rawMaterial) => String(rawMaterial.id)}
                only={['rawMaterials']}
                toolbar={
                    // Hidden below two units: a filter offering one choice narrows
                    // nothing, and a workspace that measures everything in pieces
                    // should not carry a control that cannot change the answer.
                    unitsInUse.length > 1
                        ? (filter) => (
                              <FilterPanel filter={filter}>
                                  <SelectFilter
                                      value={filter.values.unit ?? ''}
                                      onChange={(unit) =>
                                          filter.set('unit', unit)
                                      }
                                      options={unitsInUse.map((unit) => ({
                                          value: unit,
                                          label: `units.name.${unit}` as const,
                                      }))}
                                      label="raw-materials.filter.unit"
                                      allLabel="raw-materials.filter.all_units"
                                  />
                              </FilterPanel>
                          )
                        : undefined
                }
                searchPlaceholder={t('raw-materials.search_placeholder')}
                noMatch={{
                    title: t('raw-materials.no_match.title'),
                    description: t('raw-materials.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Boxes}
                        title={t('raw-materials.empty.title')}
                        description={t('raw-materials.empty.description')}
                        action={<NewRawMaterialButton />}
                    />
                }
            />
        </>
    );
}
