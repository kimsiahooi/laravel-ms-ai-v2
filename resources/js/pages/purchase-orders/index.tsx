import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { NewOrderButton } from '@/pages/purchase-orders/_components/new-order-button';
import {
    ExpectedCell,
    NumberCell,
    SupplierCell,
    TotalCell,
} from '@/pages/purchase-orders/_components/order-cells';
import { OrderStatusBadge } from '@/pages/purchase-orders/_components/order-status-badge';
import { SupplierFilter } from '@/pages/purchase-orders/_components/supplier-filter';
import { index } from '@/routes/purchase-orders';
import { index as suppliersIndex } from '@/routes/suppliers';
import type { Paginated, ResourceFilters } from '@/types';
import type { TranslationKey } from '@/types/lang';

/** Generated from App\Data\PurchaseOrderData — `bun run types:generate`. */
type Order = App.Data.PurchaseOrderData;

type Props = {
    orders: Paginated<Order>;
    filters: ResourceFilters;
    /** Every supplier — the filter's options, and what says whether one exists at all. */
    suppliers: App.Data.OptionData[];
};

/**
 * The words for each status, as a `Record` over the enum rather than a hand-written
 * array: a fourth status is a compile error here instead of a value the filter quietly
 * cannot select. Insertion order is the lifecycle order, which is the order the menu
 * shows them in.
 */
const STATUS_LABEL: Record<App.Enums.PurchaseOrderStatus, TranslationKey> = {
    pending: 'purchase-orders.status.pending',
    received: 'purchase-orders.status.received',
    cancelled: 'purchase-orders.status.cancelled',
};

const STATUS_OPTIONS = Object.entries(STATUS_LABEL).map(([value, label]) => ({
    value,
    label,
}));

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 */
const column = columnsFor<Order>();

const columns = column.columns([
    column.accessor('number', {
        ...heading('purchase-orders.column.number', { width: 'max-w-[14rem]' }),
        cell: ({ row }) => <NumberCell order={row.original} />,
    }),
    column.accessor('supplier', {
        ...heading('purchase-orders.column.supplier', {
            hideBelow: 'sm',
            width: 'max-w-[16rem]',
        }),
        cell: ({ row }) => <SupplierCell supplier={row.original.supplier} />,
    }),
    column.accessor('status', {
        ...heading('purchase-orders.column.status'),
        cell: ({ row }) => <OrderStatusBadge status={row.original.status} />,
    }),
    column.accessor('total', {
        ...heading('purchase-orders.column.total', { align: 'end' }),
        cell: ({ row }) => <TotalCell order={row.original} />,
    }),
    column.accessor('expected_date', {
        ...heading('purchase-orders.column.expected', { hideBelow: 'md' }),
        cell: ({ row }) => <ExpectedCell date={row.original.expected_date} />,
    }),
    column.accessor('created_at', {
        ...heading('purchase-orders.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
]);

export default function PurchaseOrdersIndex({
    orders,
    filters,
    suppliers,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('purchase-orders.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('purchase-orders.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('purchase-orders.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('purchase-orders.subtitle')}
                    </p>
                </div>
                <NewOrderButton />
            </div>

            <DataTable
                href={index().url}
                tableKey="purchase-orders"
                page={orders}
                filters={filters}
                columns={columns}
                getRowId={(order) => String(order.id)}
                only={['orders']}
                searchPlaceholder={t('purchase-orders.search_placeholder')}
                // Both filters always show. Unlike the warehouse filters elsewhere,
                // neither needs a second row before it means anything: "the ones still
                // waiting to arrive" and "everything from this supplier" are the two
                // questions this screen is opened to answer.
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        <SelectFilter
                            value={filter.values.status ?? ''}
                            onChange={(status) => filter.set('status', status)}
                            options={STATUS_OPTIONS}
                            label="purchase-orders.filter.status"
                            allLabel="purchase-orders.filter.all_statuses"
                        />
                        <SupplierFilter
                            value={filter.values.supplier ?? ''}
                            onChange={(supplier) =>
                                filter.set('supplier', supplier)
                            }
                            options={suppliers}
                            label="purchase-orders.filter.supplier"
                            allLabel="purchase-orders.filter.all_suppliers"
                            searchPlaceholder="purchase-orders.filter.supplier_search"
                            emptyMessage="purchase-orders.filter.supplier_empty"
                        />
                    </FilterPanel>
                )}
                noMatch={{
                    title: t('purchase-orders.no_match.title'),
                    description: t('purchase-orders.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Two different nothings. An order is raised *with* somebody, so a
                    // workspace with no suppliers cannot raise one at all — and the
                    // button that would normally sit here would open a form whose first
                    // required field has no valid answer.
                    suppliers.length === 0 ? (
                        <EmptyState
                            icon={ShoppingCart}
                            title={t('purchase-orders.no_setup.title')}
                            description={t(
                                'purchase-orders.no_setup.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={suppliersIndex()}>
                                        {t('purchase-orders.no_setup.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={ShoppingCart}
                            title={t('purchase-orders.empty.title')}
                            description={t('purchase-orders.empty.description')}
                            action={<NewOrderButton />}
                        />
                    )
                }
            />
        </>
    );
}
