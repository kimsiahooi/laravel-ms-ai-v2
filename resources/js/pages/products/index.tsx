import { Head, setLayoutProps } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import {
    CategoryLink,
    SupplierLink,
} from '@/pages/products/_components/filing-links';
import { NewProductButton } from '@/pages/products/_components/new-product-button';
import { ProductActions } from '@/pages/products/_components/product-actions';
import { index } from '@/routes/products';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\ProductData — `bun run types:generate`. */
type Product = App.Data.ProductData;

type Props = {
    products: Paginated<Product>;
    filters: ResourceFilters;
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * The category is a badge rather than plain text. It is the one field on this screen
 * whose whole job is grouping, and a badge is what makes a column of them scannable —
 * you see the shape of the catalog without reading it.
 *
 * Both it and the supplier link through to their own screen — see {@see CategoryLink}.
 */
const column = columnsFor<Product>();

const columns = column.columns([
    column.accessor('name', {
        header: () => <ColumnHeader label="products.column.name" />,
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                <UnitSymbol unit={row.original.unit} />
            </>
        ),
        meta: { width: 'max-w-[20rem]' },
    }),
    column.accessor('sku', {
        header: () => <ColumnHeader label="products.column.sku" />,
        cell: ({ row }) => (
            <span className="font-mono text-muted-foreground text-xs">
                {row.original.sku}
            </span>
        ),
        meta: { hideBelow: 'sm' },
    }),
    column.accessor('category', {
        header: () => <ColumnHeader label="products.column.category" />,
        cell: ({ row }) => <CategoryLink name={row.original.category} />,
        meta: { hideBelow: 'md' },
    }),
    column.accessor('supplier', {
        header: () => <ColumnHeader label="products.column.supplier" />,
        cell: ({ row }) => <SupplierLink name={row.original.supplier} />,
        meta: { hideBelow: 'lg', width: 'max-w-[14rem] truncate' },
    }),
    column.accessor('created_at', {
        header: () => <ColumnHeader label="products.column.created" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {formatDate(row.original.created_at)}
            </span>
        ),
        meta: { hideBelow: 'xl' },
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <ProductActions product={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

/** The product's unit under its name — the short form, as on raw materials. */
function UnitSymbol({ unit }: { unit: App.Enums.Unit }) {
    const { t } = useTranslation();

    return (
        <span className="block text-muted-foreground text-xs">
            {t(`units.symbol.${unit}` as const)}
        </span>
    );
}

export default function ProductsIndex({ products, filters }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('products.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('products.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('products.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('products.subtitle')}
                    </p>
                </div>
                <NewProductButton />
            </div>

            <DataTable
                href={index().url}
                page={products}
                filters={filters}
                columns={columns}
                getRowId={(product) => String(product.id)}
                only={['products']}
                searchPlaceholder={t('products.search_placeholder')}
                noMatch={{
                    title: t('products.no_match.title'),
                    description: t('products.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Package}
                        title={t('products.empty.title')}
                        description={t('products.empty.description')}
                        action={<NewProductButton />}
                    />
                }
            />
        </>
    );
}
