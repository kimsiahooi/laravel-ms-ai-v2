import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { NewReturnButton } from '@/pages/purchase-returns/_components/new-return-button';
import {
    CreditCell,
    NumberCell,
    OrderCell,
    ReasonCell,
    SupplierCell,
} from '@/pages/purchase-returns/_components/return-cells';
import { ReturnStatusBadge } from '@/pages/purchase-returns/_components/return-status-badge';
import { index as purchaseOrders } from '@/routes/purchase-orders';
import { index } from '@/routes/purchase-returns';
import type { Paginated, ResourceFilters } from '@/types';
import type { TranslationKey } from '@/types/lang';

/** Generated from App\Data\PurchaseReturnData — `bun run types:generate`. */
type Return = App.Data.PurchaseReturnData;

type Props = {
    returns: Paginated<Return>;
    filters: ResourceFilters;
    /**
     * Whether anything has ever been received.
     *
     * The empty state's whole question: a list with no rows because nobody has returned
     * anything wants a different sentence from one with no rows because nothing has arrived
     * to return. The second has no button worth offering.
     */
    anyReceived: boolean;
};

/**
 * Both filters as `Record`s over their enums rather than hand-written arrays: a case added
 * server-side is a compile error here instead of a value the filter quietly cannot select.
 * Insertion order is the lifecycle order for one and the catalogue order for the other, which
 * is the order each menu shows them in.
 */
const STATUS_LABEL: Record<App.Enums.ReturnStatus, TranslationKey> = {
    pending: 'returns.status.pending',
    completed: 'returns.status.completed',
    cancelled: 'returns.status.cancelled',
};

const REASON_LABEL: Record<App.Enums.ReturnReason, TranslationKey> = {
    damaged: 'returns.reason.damaged',
    wrong_item: 'returns.reason.wrong_item',
    quality: 'returns.reason.quality',
    surplus: 'returns.reason.surplus',
    other: 'returns.reason.other',
};

const asOptions = (labels: Record<string, TranslationKey>) =>
    Object.entries(labels).map(([value, label]) => ({ value, label }));

const STATUS_OPTIONS = asOptions(STATUS_LABEL);
const REASON_OPTIONS = asOptions(REASON_LABEL);

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one each
 * render rebuilds every column instance.
 */
const column = columnsFor<Return>();

const columns = column.columns([
    column.accessor('number', {
        ...heading('purchase-returns.column.number', {
            width: 'max-w-[12rem]',
        }),
        cell: ({ row }) => <NumberCell row={row.original} />,
    }),
    column.display({
        id: 'order',
        // Not sortable: it lives on another table, reached through a join this list has no
        // business writing. The search box covers it instead.
        ...heading('purchase-returns.column.order', {
            hideBelow: 'sm',
            width: 'max-w-[12rem]',
        }),
        cell: ({ row }) => <OrderCell row={row.original} />,
    }),
    column.display({
        id: 'supplier',
        ...heading('purchase-returns.column.supplier', {
            hideBelow: 'lg',
            width: 'max-w-[14rem]',
        }),
        cell: ({ row }) => <SupplierCell supplier={row.original.supplier} />,
    }),
    column.accessor('status', {
        ...heading('purchase-returns.column.status'),
        cell: ({ row }) => <ReturnStatusBadge status={row.original.status} />,
    }),
    column.display({
        id: 'reason',
        ...heading('purchase-returns.column.reason', { hideBelow: 'md' }),
        cell: ({ row }) => <ReasonCell reason={row.original.reason} />,
    }),
    column.accessor('total', {
        // Not sortable, for the reason the orders list gives about its own: a return is
        // denominated in the order's currency and ranking the column would present 900 MYR
        // above 500 USD as an answer.
        ...heading('purchase-returns.column.total', { align: 'end' }),
        cell: ({ row }) => <CreditCell row={row.original} />,
    }),
    column.accessor('created_at', {
        ...heading('purchase-returns.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
]);

/**
 * Goods going back to suppliers.
 *
 * The two filters are the two questions this screen is opened to answer: what is still
 * outstanding, and why things are being sent back. Neither needs a second row before it means
 * anything, so both always show.
 */
export default function PurchaseReturnsIndex({
    returns,
    filters,
    anyReceived,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('purchase-returns.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('purchase-returns.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('purchase-returns.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('purchase-returns.subtitle')}
                    </p>
                </div>
                {anyReceived && <NewReturnButton />}
            </div>

            <DataTable
                href={index().url}
                tableKey="purchase-returns"
                page={returns}
                filters={filters}
                columns={columns}
                getRowId={(row) => String(row.id)}
                only={['returns']}
                searchPlaceholder={t('purchase-returns.search_placeholder')}
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        <SelectFilter
                            value={filter.values.status ?? ''}
                            onChange={(status) => filter.set('status', status)}
                            options={STATUS_OPTIONS}
                            label="purchase-returns.filter.status"
                            allLabel="purchase-returns.filter.all_statuses"
                        />
                        <SelectFilter
                            value={filter.values.reason ?? ''}
                            onChange={(reason) => filter.set('reason', reason)}
                            options={REASON_OPTIONS}
                            label="purchase-returns.filter.reason"
                            allLabel="purchase-returns.filter.all_reasons"
                        />
                    </FilterPanel>
                )}
                noMatch={{
                    title: t('purchase-returns.no_match.title'),
                    description: t('purchase-returns.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    // Two different nothings. Goods can only go back once they have
                    // arrived, so a workspace that has received nothing cannot raise a
                    // return at all — and the button that would normally sit here would
                    // lead to a list with nothing on it.
                    anyReceived ? (
                        <EmptyState
                            icon={Undo2}
                            title={t('purchase-returns.empty.title')}
                            description={t(
                                'purchase-returns.empty.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link
                                        href={purchaseOrders(undefined, {
                                            query: { status: 'received' },
                                        })}
                                    >
                                        {t('purchase-returns.empty.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icon={Undo2}
                            title={t('purchase-returns.no_setup.title')}
                            description={t(
                                'purchase-returns.no_setup.description',
                            )}
                            action={
                                <Button variant="outline" asChild>
                                    <Link href={purchaseOrders()}>
                                        {t('purchase-returns.no_setup.action')}
                                    </Link>
                                </Button>
                            }
                        />
                    )
                }
            />
        </>
    );
}
