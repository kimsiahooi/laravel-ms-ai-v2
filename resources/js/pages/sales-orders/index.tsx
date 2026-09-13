import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { SingleComboboxFilter } from '@/components/data/single-combobox-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { NewOrderButton } from '@/pages/sales-orders/_components/new-order-button';
import {
    CustomerCell,
    ExpectedCell,
    NumberCell,
    TotalCell,
} from '@/pages/sales-orders/_components/order-cells';
import { OrderStatusBadge } from '@/pages/sales-orders/_components/order-status-badge';
import { index as customersIndex } from '@/routes/customers';
import { index } from '@/routes/sales-orders';
import type { Paginated, ResourceFilters } from '@/types';
import type { TranslationKey } from '@/types/lang';

/** Generated from App\Data\SalesOrderData — `bun run types:generate`. */
type Order = App.Data.SalesOrderData;

type Props = {
    orders: Paginated<Order>;
    filters: ResourceFilters;
    /** Every customer — the filter's options, and what says whether one exists at all. */
    customers: App.Data.OptionData[];
};

/**
 * The words for each status, as a `Record` over the enum rather than a hand-written array:
 * a fourth status is a compile error here instead of a value the filter quietly cannot
 * select. Insertion order is the lifecycle order, which is the order the menu shows them in.
 */
const STATUS_LABEL: Record<App.Enums.SalesOrderStatus, TranslationKey> = {
    pending: 'sales-orders.status.pending',
    fulfilled: 'sales-orders.status.fulfilled',
    cancelled: 'sales-orders.status.cancelled',
};

const STATUS_OPTIONS = Object.entries(STATUS_LABEL).map(([value, label]) => ({
    value,
    label,
}));

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one each
 * render rebuilds every column instance.
 */
const column = columnsFor<Order>();

const columns = column.columns([
    column.accessor('number', {
        ...heading('sales-orders.column.number', { width: 'max-w-[14rem]' }),
        cell: ({ row }) => <NumberCell order={row.original} />,
    }),
    column.accessor('customer', {
        ...heading('sales-orders.column.customer', {
            hideBelow: 'sm',
            width: 'max-w-[16rem]',
        }),
        cell: ({ row }) => <CustomerCell customer={row.original.customer} />,
    }),
    column.accessor('status', {
        ...heading('sales-orders.column.status'),
        cell: ({ row }) => <OrderStatusBadge status={row.original.status} />,
    }),
    column.accessor('total', {
        ...heading('sales-orders.column.total', { align: 'end' }),
        cell: ({ row }) => <TotalCell order={row.original} />,
    }),
    column.accessor('expected_date', {
        ...heading('sales-orders.column.expected', { hideBelow: 'md' }),
        cell: ({ row }) => <ExpectedCell date={row.original.expected_date} />,
    }),
    column.accessor('created_at', {
        ...heading('sales-orders.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
]);

export default function SalesOrdersIndex({
    orders,
    filters,
    customers,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('sales-orders.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('sales-orders.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('sales-orders.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('sales-orders.subtitle')}
                    </p>
                </div>
                <NewOrderButton />
            </div>

            <DataTable
                href={index().url}
                tableKey="sales-orders"
                page={orders}
                filters={filters}
                columns={columns}
                getRowId={(order) => String(order.id)}
                only={['orders']}
                searchPlaceholder={t('sales-orders.search_placeholder')}
                // Both filters always show. Neither needs a second row before it means
                // anything: "the ones still to ship" and "everything for this customer" are
                // the two questions this screen is opened to answer.
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        <SelectFilter
                            value={filter.values.status ?? ''}
                            onChange={(status) => filter.set('status', status)}
                            options={STATUS_OPTIONS}
                            label="sales-orders.filter.status"
                            allLabel="sales-orders.filter.all_statuses"
                        />
                        <SingleComboboxFilter
                            value={filter.values.customer ?? ''}
                            onChange={(customer) =>
                                filter.set('customer', customer)
                            }
                            options={customers}
                            label="sales-orders.filter.customer"
                            allLabel="sales-orders.filter.all_customers"
                            searchPlaceholder="sales-orders.filter.customer_search"
                            emptyMessage="sales-orders.filter.customer_empty"
                        />
                    </FilterPanel>
                )}
                noMatch={{
                    title: t('sales-orders.no_match.title'),
                    description: t('sales-orders.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Two different nothings. An order is taken *from* somebody, so a
                    // workspace with no customers cannot take one at all — and the button
                    // that would normally sit here would open a form whose first required
                    // field has no valid answer.
                    customers.length === 0 ? (
                        <EmptyState
                            icon={Receipt}
                            title={t('sales-orders.no_setup.title')}
                            description={t('sales-orders.no_setup.description')}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={customersIndex()}>
                                        {t('sales-orders.no_setup.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Receipt}
                            title={t('sales-orders.empty.title')}
                            description={t('sales-orders.empty.description')}
                            action={<NewOrderButton />}
                        />
                    )
                }
            />
        </>
    );
}
