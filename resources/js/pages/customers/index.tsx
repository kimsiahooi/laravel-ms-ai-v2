import { Head, setLayoutProps } from '@inertiajs/react';
import { Building } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { CustomerActions } from '@/pages/customers/_components/customer-actions';
import { NewCustomerButton } from '@/pages/customers/_components/new-customer-button';
import { index } from '@/routes/customers';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\CustomerData — `bun run types:generate`. */
type Customer = App.Data.CustomerData;

type Props = {
    customers: Paginated<Customer>;
    filters: ResourceFilters;
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * Thirteen fields, five columns. The table answers "which customer is this and how do I
 * reach them"; the tax identity only matters when an invoice is being built, and putting
 * a TIN on screen would cost the space that makes the name readable.
 */
const column = columnsFor<Customer>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('customers.column.name', { width: 'max-w-[18rem]' }),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {row.original.contact_person && (
                    <span className="block truncate text-muted-foreground text-xs">
                        {row.original.contact_person}
                    </span>
                )}
            </>
        ),
    }),
    column.accessor('email', {
        ...heading('customers.column.email', {
            hideBelow: 'sm',
            width: 'max-w-[16rem] truncate',
        }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.email ?? '—'}
            </span>
        ),
    }),
    column.display({
        // Not a column on the model: city and country read as one fact, and neither is
        // worth a column of its own. Display-only, so nothing offers to sort by it.
        id: 'location',
        ...heading('customers.column.location', { hideBelow: 'md' }),
        cell: ({ row }) => <Location customer={row.original} />,
    }),
    column.accessor('created_at', {
        ...heading('customers.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.accessor('creator', {
        ...heading('customers.column.creator', { hideBelow: 'xl' }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* i18n-allow */}
                {row.original.creator ?? '—'}
            </span>
        ),
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <CustomerActions customer={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

/**
 * Where a customer is, as one line. The country is a code on the wire and a name on
 * screen, resolved through `countries.{CODE}` like everywhere else.
 */
function Location({ customer }: { customer: Customer }) {
    const { t } = useTranslation();
    const parts = [
        customer.city,
        customer.country_code
            ? t(`countries.${customer.country_code}` as const)
            : null,
    ].filter(Boolean);

    return (
        <span className="text-muted-foreground">
            {/* i18n-allow */}
            {parts.length > 0 ? parts.join(', ') : '—'}
        </span>
    );
}

export default function CustomersIndex({ customers, filters }: Props) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `CustomersIndex.layout`: a breadcrumb title is
    // a resolved string, and resolving one needs t(), which cannot run at module scope.
    setLayoutProps({
        breadcrumbs: [{ title: t('customers.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('customers.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('customers.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('customers.subtitle')}
                    </p>
                </div>
                <NewCustomerButton />
            </div>

            <DataTable
                href={index().url}
                tableKey="customers"
                page={customers}
                filters={filters}
                columns={columns}
                getRowId={(customer) => String(customer.id)}
                only={['customers']}
                searchPlaceholder={t('customers.search_placeholder')}
                noMatch={{
                    title: t('customers.no_match.title'),
                    description: t('customers.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Building}
                        title={t('customers.empty.title')}
                        description={t('customers.empty.description')}
                        action={<NewCustomerButton />}
                    />
                }
            />
        </>
    );
}
