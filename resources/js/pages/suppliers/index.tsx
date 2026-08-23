import { Head, setLayoutProps } from '@inertiajs/react';
import { Truck } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { NewSupplierButton } from '@/pages/suppliers/_components/new-supplier-button';
import { SupplierActions } from '@/pages/suppliers/_components/supplier-actions';
import { index } from '@/routes/suppliers';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\SupplierData — `bun run types:generate`. */
type Supplier = App.Data.SupplierData;

type Props = {
    suppliers: Paginated<Supplier>;
    filters: ResourceFilters;
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * The contact person rides under the company name rather than taking a column of its
 * own. "Acme Steel / Lim Wei" reads as one thing — the supplier and the human at it —
 * and it survives a phone, where a fifth column would not.
 */
const column = columnsFor<Supplier>();

const columns = column.columns([
    column.accessor('name', {
        header: () => <ColumnHeader label="suppliers.column.name" />,
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
        meta: { width: 'max-w-[18rem]' },
    }),
    column.accessor('email', {
        header: () => <ColumnHeader label="suppliers.column.email" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.email ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'sm', width: 'max-w-[16rem] truncate' },
    }),
    column.accessor('phone', {
        header: () => <ColumnHeader label="suppliers.column.phone" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {/* i18n-allow */}
                {row.original.phone ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'md' },
    }),
    column.accessor('created_at', {
        header: () => <ColumnHeader label="suppliers.column.created" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {formatDate(row.original.created_at)}
            </span>
        ),
        meta: { hideBelow: 'lg' },
    }),
    column.accessor('creator', {
        header: () => <ColumnHeader label="suppliers.column.creator" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* i18n-allow */}
                {row.original.creator ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'xl' },
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <SupplierActions supplier={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

export default function SuppliersIndex({ suppliers, filters }: Props) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `SuppliersIndex.layout`: a breadcrumb title
    // is a resolved string, and resolving one needs t(), which cannot run at module
    // scope. TenantLayout supplies the workspace crumb ahead of this one.
    setLayoutProps({
        breadcrumbs: [{ title: t('suppliers.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('suppliers.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('suppliers.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('suppliers.subtitle')}
                    </p>
                </div>
                <NewSupplierButton />
            </div>

            <DataTable
                href={index().url}
                page={suppliers}
                filters={filters}
                columns={columns}
                getRowId={(supplier) => String(supplier.id)}
                only={['suppliers']}
                searchPlaceholder={t('suppliers.search_placeholder')}
                noMatch={{
                    title: t('suppliers.no_match.title'),
                    description: t('suppliers.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Truck}
                        title={t('suppliers.empty.title')}
                        description={t('suppliers.empty.description')}
                        action={<NewSupplierButton />}
                    />
                }
            />
        </>
    );
}
