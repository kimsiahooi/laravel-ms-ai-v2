import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { ReorderLevelCell } from '@/pages/warehouses/_components/reorder-level-cell';
import { WarehouseEmpty } from '@/pages/warehouses/_components/warehouse-empty';
import { WarehouseItemCell } from '@/pages/warehouses/_components/warehouse-item-cell';
import { WarehouseSummary } from '@/pages/warehouses/_components/warehouse-summary';
import { index as movements } from '@/routes/stock-movements';
import { index, show } from '@/routes/warehouses';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\WarehouseItemData — `bun run types:generate`. */
type Item = App.Data.WarehouseItemData;

type Props = {
    warehouse: App.Data.WarehouseData;
    items: Paginated<Item>;
    summary: { in_stock: number; needs_reorder: number };
    filters: ResourceFilters;
    /** Whether the workspace has a catalogue at all — which of two nothings this is. */
    hasItems: boolean;
};

/**
 * Built once at module scope, like every other list's — TanStack treats the array as an
 * input and a fresh one each render rebuilds every column instance. That is also why
 * the editable cell reads the warehouse off the page rather than taking it as a prop.
 *
 * **The reorder box is never the column that gives way.** It is what somebody came
 * here to use, and three columns plus an input do not fit in 375 pixels — so on hand
 * steps aside below `sm` and reappears under the item's name, where it is still beside
 * the badge that depends on it. Type and SKU ride there too rather than taking columns
 * of their own, which is where the rest of the width comes from.
 */
const column = columnsFor<Item>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('warehouses.detail.column.item', {
            width: 'max-w-[13rem] sm:max-w-[22rem]',
        }),
        cell: ({ row }) => <WarehouseItemCell item={row.original} />,
    }),
    column.accessor('sku', {
        ...heading('warehouses.detail.column.sku', {
            hideBelow: 'lg',
            defaultHidden: true,
        }),
        cell: ({ row }) => (
            <span className="font-mono text-muted-foreground text-xs">
                {row.original.sku}
            </span>
        ),
    }),
    column.accessor('on_hand', {
        ...heading('warehouses.detail.column.on_hand', {
            align: 'end',
            hideBelow: 'sm',
        }),
        cell: ({ row }) => (
            <span className="whitespace-nowrap tabular-nums">
                {row.original.on_hand} <UnitSymbol unit={row.original.unit} />
            </span>
        ),
    }),
    column.accessor('min_stock', {
        ...heading('warehouses.detail.column.level', { align: 'end' }),
        cell: ({ row }) => <ReorderLevelCell item={row.original} />,
    }),
]);

/** The unit's short form, muted so the number stays the thing being read. */
function UnitSymbol({ unit }: { unit: App.Enums.Unit }) {
    const { t } = useTranslation();

    return (
        <span className="text-muted-foreground text-xs">
            {t(`units.symbol.${unit}` as const)}
        </span>
    );
}

export default function WarehouseShow({
    warehouse,
    items,
    summary,
    filters,
    hasItems,
}: Props) {
    const { t } = useTranslation();

    const href = show({ warehouse: warehouse.id }).url;

    // The site is what tells two "Main store"s apart; the code and the address are what
    // somebody standing in the building would recognise it by.
    const subline = [warehouse.location, warehouse.code, warehouse.address]
        .filter(Boolean)
        .join(' · ');

    setLayoutProps({
        breadcrumbs: [
            { title: t('warehouses.title'), href: index() },
            { title: warehouse.name, href },
        ],
    });

    return (
        <>
            <Head title={warehouse.name} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {warehouse.name}
                    </h1>
                    <p className="text-muted-foreground text-sm">{subline}</p>
                </div>
                <Button variant="outline" asChild>
                    <Link
                        href={movements(undefined, {
                            query: { warehouse: String(warehouse.id) },
                        })}
                    >
                        {t('warehouses.detail.view_movements')}
                    </Link>
                </Button>
            </div>

            <WarehouseSummary summary={summary} />

            {/*
                Above the table rather than in a tooltip on the column: the reorder
                column is an input, which is unusual enough to be worth one sentence,
                and a sentence somebody has to hover to find is a sentence for people on
                a mouse only.
            */}
            <p className="text-muted-foreground text-sm">
                {t('warehouses.detail.level_hint')}
            </p>

            <DataTable
                href={href}
                tableKey="warehouse-items"
                page={items}
                filters={filters}
                columns={columns}
                getRowId={(item) => item.item}
                only={['items', 'summary']}
                searchPlaceholder={t('warehouses.detail.search_placeholder')}
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        {/*
                            The "no filter" entry here is not "everything", and that is
                            deliberate. A catalogue of five hundred items in a warehouse
                            holding forty is four hundred and sixty rows of zero, so the
                            unfiltered list is what this warehouse has to do with —
                            stock on the shelf, or a level somebody set — and the whole
                            catalogue is the deliberate widening.
                        */}
                        <SelectFilter
                            value={filter.values.show ?? ''}
                            onChange={(show) => filter.set('show', show)}
                            options={[
                                {
                                    value: 'attention',
                                    label: 'warehouses.detail.filter.attention',
                                },
                                {
                                    value: 'all',
                                    label: 'warehouses.detail.filter.all',
                                },
                            ]}
                            label="warehouses.detail.filter.show"
                            allLabel="warehouses.detail.filter.stocked"
                        />
                    </FilterPanel>
                )}
                noMatch={{
                    title: t('warehouses.detail.no_match.title'),
                    description: t('warehouses.detail.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <WarehouseEmpty
                        warehouseId={warehouse.id}
                        hasItems={hasItems}
                    />
                }
            />
        </>
    );
}
