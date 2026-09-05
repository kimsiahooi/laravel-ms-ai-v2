import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { ComboboxFilter } from '@/components/data/combobox-filter';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import {
    ItemCell,
    ReasonCell,
    WarehouseCell,
} from '@/pages/stock-movements/_components/movement-cells';
import { QuantityCell } from '@/pages/stock-movements/_components/quantity-cell';
import { RecordMovementButton } from '@/pages/stock-movements/_components/record-movement-button';
import { index as products } from '@/routes/products';
import { index } from '@/routes/stock-movements';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\StockMovementData — `bun run types:generate`. */
type Movement = App.Data.StockMovementData;

type Props = {
    movements: Paginated<Movement>;
    filters: ResourceFilters;
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
    reasonsUsed: App.Enums.StockMovementReason[];
};

const column = columnsFor<Movement>();

const columns = column.columns([
    column.accessor('item', {
        header: () => <ColumnHeader label="stock-movements.column.item" />,
        cell: ({ row }) => <ItemCell movement={row.original} />,
        meta: { width: 'max-w-[18rem]' },
    }),
    column.accessor('quantity', {
        header: () => <ColumnHeader label="stock-movements.column.quantity" />,
        cell: ({ row }) => <QuantityCell quantity={row.original.quantity} />,
        meta: { align: 'end' },
    }),
    column.accessor('warehouse', {
        header: () => <ColumnHeader label="stock-movements.column.warehouse" />,
        cell: ({ row }) => <WarehouseCell movement={row.original} />,
        meta: { hideBelow: 'md', width: 'max-w-[12rem]' },
    }),
    column.accessor('reason', {
        header: () => <ColumnHeader label="stock-movements.column.reason" />,
        cell: ({ row }) => <ReasonCell reason={row.original.reason} />,
        meta: { hideBelow: 'lg' },
    }),
    column.accessor('created_at', {
        header: () => <ColumnHeader label="stock-movements.column.recorded" />,
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
        meta: { hideBelow: 'sm' },
    }),
    column.accessor('user', {
        header: () => <ColumnHeader label="stock-movements.column.user" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* Null once the person has been removed. i18n-allow */}
                {row.original.user ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'xl' },
    }),
]);

export default function StockMovementsIndex({
    movements,
    filters,
    warehouses,
    items,
    reasonsUsed,
}: Props) {
    const { t } = useTranslation();

    // One warehouse is not a filter — every movement is in it.
    const showWarehouseFilter = warehouses.length > 1;
    const showReasonFilter = reasonsUsed.length > 1;

    setLayoutProps({
        breadcrumbs: [{ title: t('stock-movements.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('stock-movements.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('stock-movements.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('stock-movements.subtitle')}
                    </p>
                </div>
                <RecordMovementButton />
            </div>

            <DataTable
                href={index().url}
                page={movements}
                filters={filters}
                columns={columns}
                getRowId={(movement) => String(movement.id)}
                only={['movements']}
                searchPlaceholder={t('stock-movements.search_placeholder')}
                toolbar={
                    showWarehouseFilter || showReasonFilter
                        ? (filter) => (
                              <FilterPanel filter={filter}>
                                  {showWarehouseFilter && (
                                      <ComboboxFilter
                                          value={filter.values.warehouse ?? ''}
                                          onChange={(warehouse) =>
                                              filter.set('warehouse', warehouse)
                                          }
                                          options={warehouses}
                                          label="stock-movements.filter.warehouse"
                                          allLabel="stock-movements.filter.all_warehouses"
                                          manyLabel="stock-movements.filter.warehouses_selected"
                                          searchPlaceholder="stock-movements.filter.warehouse_search"
                                          emptyMessage="stock-movements.filter.warehouse_empty"
                                          hint="stock-movements.filter.warehouse_hint"
                                      />
                                  )}

                                  {showReasonFilter && (
                                      <SelectFilter
                                          value={filter.values.reason ?? ''}
                                          onChange={(reason) =>
                                              filter.set('reason', reason)
                                          }
                                          options={reasonsUsed.map(
                                              (reason) => ({
                                                  value: reason,
                                                  label: `stock-movements.reason.${reason}` as const,
                                              }),
                                          )}
                                          label="stock-movements.filter.reason"
                                          allLabel="stock-movements.filter.all_reasons"
                                      />
                                  )}
                              </FilterPanel>
                          )
                        : undefined
                }
                noMatch={{
                    title: t('stock-movements.no_match.title'),
                    description: t('stock-movements.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Three different nothings, and they need different answers. With no
                    // warehouse there is nowhere for stock to be; with nothing in the
                    // catalogue there is nothing to move. Only when both exist is an
                    // empty ledger simply an empty ledger.
                    warehouses.length === 0 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-movements.no_setup.title')}
                            description={t(
                                'stock-movements.no_setup.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={warehousesIndex()}>
                                        {t('stock-movements.no_setup.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : items.length === 0 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-movements.no_items.title')}
                            description={t(
                                'stock-movements.no_items.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={products()}>
                                        {t('stock-movements.no_items.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-movements.empty.title')}
                            description={t('stock-movements.empty.description')}
                            action={<RecordMovementButton />}
                        />
                    )
                }
            />
        </>
    );
}
