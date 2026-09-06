import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { CheckboxFilter } from '@/components/data/checkbox-filter';
import { heading } from '@/components/data/column-header';
import { ComboboxFilter } from '@/components/data/combobox-filter';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { NotesCell } from '@/components/data/notes-cell';
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
import { SourceCell } from '@/pages/stock-movements/_components/source-cell';
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
        ...heading('stock-movements.column.item', { width: 'max-w-[18rem]' }),
        cell: ({ row }) => <ItemCell movement={row.original} />,
    }),
    column.accessor('quantity', {
        ...heading('stock-movements.column.quantity', { align: 'end' }),
        cell: ({ row }) => <QuantityCell quantity={row.original.quantity} />,
    }),
    column.accessor('warehouse', {
        ...heading('stock-movements.column.warehouse', {
            hideBelow: 'md',
            width: 'max-w-[12rem]',
        }),
        cell: ({ row }) => <WarehouseCell movement={row.original} />,
    }),
    column.accessor('reason', {
        ...heading('stock-movements.column.reason', { hideBelow: 'lg' }),
        cell: ({ row }) => <ReasonCell reason={row.original.reason} />,
    }),
    // Beside the reason, which is the coarse version of the same question: `reason`
    // says a stock take did this, `source` says which one.
    column.accessor('source_id', {
        ...heading('stock-movements.column.source', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <SourceCell
                type={row.original.source_type}
                id={row.original.source_id}
            />
        ),
    }),
    // No `hideBelow`: a column you had to ask for should appear once you have asked.
    column.accessor('notes', {
        ...heading('stock-movements.column.notes', {
            defaultHidden: true,
            width: 'max-w-[20rem]',
        }),
        cell: ({ row }) => <NotesCell notes={row.original.notes} />,
    }),
    column.accessor('created_at', {
        ...heading('stock-movements.column.recorded', { hideBelow: 'sm' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.accessor('user', {
        ...heading('stock-movements.column.user', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* Null once the person has been removed. i18n-allow */}
                {row.original.user ?? '—'}
            </span>
        ),
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
                tableKey="stock-movements"
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
                                      <CheckboxFilter
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
                                          manyLabel="stock-movements.filter.reasons_selected"
                                          hint="stock-movements.filter.reason_hint"
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
