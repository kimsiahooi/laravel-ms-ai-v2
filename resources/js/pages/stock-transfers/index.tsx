import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';
import { heading } from '@/components/data/column-header';
import { ComboboxFilter } from '@/components/data/combobox-filter';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { TransferButton } from '@/pages/stock-transfers/_components/transfer-button';
import {
    EndpointCell,
    ItemCell,
    NotesCell,
    QuantityCell,
} from '@/pages/stock-transfers/_components/transfer-cells';
import { index as products } from '@/routes/products';
import { index } from '@/routes/stock-transfers';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\StockTransferData — `bun run types:generate`. */
type Transfer = App.Data.StockTransferData;

type Props = {
    transfers: Paginated<Transfer>;
    filters: ResourceFilters;
    warehouses: App.Data.WarehouseOptionData[];
    items: App.Data.StockItemOptionData[];
};

const column = columnsFor<Transfer>();

const columns = column.columns([
    column.accessor('item', {
        ...heading('stock-transfers.column.item', { width: 'max-w-[18rem]' }),
        cell: ({ row }) => <ItemCell transfer={row.original} />,
    }),
    column.accessor('from_warehouse', {
        ...heading('stock-transfers.column.from', {
            hideBelow: 'md',
            width: 'max-w-[12rem]',
        }),
        cell: ({ row }) => (
            <EndpointCell
                warehouse={row.original.from_warehouse}
                site={row.original.from_site}
            />
        ),
    }),
    column.accessor('to_warehouse', {
        ...heading('stock-transfers.column.to', {
            hideBelow: 'md',
            width: 'max-w-[12rem]',
        }),
        cell: ({ row }) => (
            <EndpointCell
                warehouse={row.original.to_warehouse}
                site={row.original.to_site}
            />
        ),
    }),
    column.accessor('quantity', {
        ...heading('stock-transfers.column.quantity', { align: 'end' }),
        cell: ({ row }) => <QuantityCell quantity={row.original.quantity} />,
    }),
    // No `hideBelow`: a column you had to ask for should appear once you have asked.
    column.accessor('notes', {
        ...heading('stock-transfers.column.notes', {
            defaultHidden: true,
            width: 'max-w-[20rem]',
        }),
        cell: ({ row }) => <NotesCell notes={row.original.notes} />,
    }),
    column.accessor('created_at', {
        ...heading('stock-transfers.column.moved', { hideBelow: 'sm' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.accessor('user', {
        ...heading('stock-transfers.column.user', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* Null once the person has been removed. i18n-allow */}
                {row.original.user ?? '—'}
            </span>
        ),
    }),
]);

export default function StockTransfersIndex({
    transfers,
    filters,
    warehouses,
    items,
}: Props) {
    const { t } = useTranslation();

    // One warehouse is not a filter — and with only one there is nothing to transfer
    // between either, so the empty state below has already said so.
    const showWarehouseFilter = warehouses.length > 1;

    setLayoutProps({
        breadcrumbs: [{ title: t('stock-transfers.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('stock-transfers.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('stock-transfers.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('stock-transfers.subtitle')}
                    </p>
                </div>
                <TransferButton />
            </div>

            <DataTable
                href={index().url}
                tableKey="stock-transfers"
                page={transfers}
                filters={filters}
                columns={columns}
                getRowId={(transfer) => String(transfer.id)}
                only={['transfers']}
                searchPlaceholder={t('stock-transfers.search_placeholder')}
                toolbar={
                    showWarehouseFilter
                        ? (filter) => (
                              <FilterPanel filter={filter}>
                                  <ComboboxFilter
                                      value={filter.values.warehouse ?? ''}
                                      onChange={(warehouse) =>
                                          filter.set('warehouse', warehouse)
                                      }
                                      options={warehouses}
                                      label="stock-transfers.filter.warehouse"
                                      allLabel="stock-transfers.filter.all_warehouses"
                                      manyLabel="stock-transfers.filter.warehouses_selected"
                                      searchPlaceholder="stock-transfers.filter.warehouse_search"
                                      emptyMessage="stock-transfers.filter.warehouse_empty"
                                      hint="stock-transfers.filter.warehouse_hint"
                                  />
                              </FilterPanel>
                          )
                        : undefined
                }
                noMatch={{
                    title: t('stock-transfers.no_match.title'),
                    description: t('stock-transfers.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Three different nothings, and they need different answers. A
                    // transfer needs two warehouses, not one — so unlike the ledger the
                    // setup state is about having a *second* place to move stock to.
                    warehouses.length < 2 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-transfers.no_setup.title')}
                            description={t(
                                'stock-transfers.no_setup.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={warehousesIndex()}>
                                        {t('stock-transfers.no_setup.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : items.length === 0 ? (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-transfers.no_items.title')}
                            description={t(
                                'stock-transfers.no_items.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={products()}>
                                        {t('stock-transfers.no_items.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={ArrowLeftRight}
                            title={t('stock-transfers.empty.title')}
                            description={t('stock-transfers.empty.description')}
                            action={<TransferButton />}
                        />
                    )
                }
            />
        </>
    );
}
