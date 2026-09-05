import { Head, setLayoutProps } from '@inertiajs/react';
import { Package } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import {
    CategoryLink,
    SupplierLink,
} from '@/pages/products/_components/filing-links';
import { NewProductButton } from '@/pages/products/_components/new-product-button';
import { ProductActions } from '@/pages/products/_components/product-actions';
import { ProductThumb } from '@/pages/products/_components/product-thumb';
import { index } from '@/routes/products';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\ProductData — `bun run types:generate`. */
type Product = App.Data.ProductData;

type Props = {
    products: Paginated<Product>;
    filters: ResourceFilters;
    /** Unit codes in use, for the filter. See the controller's unitsInUse(). */
    unitsInUse: App.Enums.Unit[];
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance.
 *
 * The photo rides in the name cell rather than in a column of its own. It identifies the
 * same thing the name does, and a separate column would be mostly empty frames taking
 * width from the fields that carry information.
 *
 * Category and supplier both link through to their own screen — see {@see CategoryLink}.
 */
const column = columnsFor<Product>();

const columns = column.columns([
    column.accessor('name', {
        header: () => <ColumnHeader label="products.column.name" />,
        cell: ({ row }) => (
            <div className="flex items-center gap-3">
                {/* Empty alt: the name is right there, and a screen reader reading the
                    product twice per row is worse than not describing the picture. */}
                <ProductThumb src={row.original.thumb_url} alt="" />
                <div className="min-w-0">
                    <span className="block truncate font-medium">
                        {row.original.name}
                    </span>
                    <NameMeta product={row.original} />
                </div>
            </div>
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
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
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

/**
 * The line under a product's name: the unit it is counted in, and the size of its bill
 * of materials where it has one.
 *
 * The bill is the only way to tell a manufactured product from a bought one, and it is
 * otherwise two clicks deep in a menu. A count rather than a badge, because "3
 * materials" answers the next question as well as the first.
 */
function NameMeta({ product }: { product: Product }) {
    const { t, tChoice } = useTranslation();

    return (
        <span className="block truncate text-muted-foreground text-xs">
            {t(`units.symbol.${product.unit}` as const)}
            {product.bom.length > 0 && (
                <>
                    {' · '}
                    {tChoice('products.bom.count', product.bom.length)}
                </>
            )}
        </span>
    );
}

export default function ProductsIndex({
    products,
    filters,
    unitsInUse,
}: Props) {
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
                toolbar={
                    // Hidden below two units: a filter offering one choice narrows
                    // nothing, and a workspace that measures everything in pieces
                    // should not carry a control that cannot change the answer.
                    unitsInUse.length > 1
                        ? (filter) => (
                              <FilterPanel filter={filter}>
                                  <SelectFilter
                                      value={filter.values.unit ?? ''}
                                      onChange={(unit) =>
                                          filter.set('unit', unit)
                                      }
                                      options={unitsInUse.map((unit) => ({
                                          value: unit,
                                          label: `units.name.${unit}` as const,
                                      }))}
                                      label="products.filter.unit"
                                      allLabel="products.filter.all_units"
                                  />
                              </FilterPanel>
                          )
                        : undefined
                }
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
