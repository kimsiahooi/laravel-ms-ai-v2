import { Head, setLayoutProps } from '@inertiajs/react';
import { Tags } from 'lucide-react';
import { ColumnHeader } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { CategoryActions } from '@/pages/categories/_components/category-actions';
import { NewCategoryButton } from '@/pages/categories/_components/new-category-button';
import { index } from '@/routes/categories';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\CategoryData — `bun run types:generate`. */
type Category = App.Data.CategoryData;

type Props = {
    categories: Paginated<Category>;
    filters: ResourceFilters;
};

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one
 * each render rebuilds every column instance. That is also why the row menu owns its
 * own dialogs — see CategoryActions — rather than calling back into page state.
 *
 * A column's id is the column the server would sort by. Which of them are actually
 * clickable is decided by the controller's allow-list, which arrives in
 * `filters.sortable`; nothing here declares it. `description` and `creator` are not on
 * that list, so their headers render plain.
 */
const column = columnsFor<Category>();

const columns = column.columns([
    column.accessor('name', {
        header: () => <ColumnHeader label="categories.column.name" />,
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {/* The description has no column of its own on a phone, so it rides
                    along under the name rather than being lost entirely. */}
                {row.original.description && (
                    <span className="block max-w-[16rem] truncate text-muted-foreground text-xs sm:hidden">
                        {row.original.description}
                    </span>
                )}
            </>
        ),
    }),
    column.accessor('description', {
        header: () => <ColumnHeader label="categories.column.description" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* A dash, not a word: nothing here to translate. i18n-allow */}
                {row.original.description ?? '—'}
            </span>
        ),
        meta: { hideBelow: 'sm', width: 'max-w-md truncate' },
    }),
    column.accessor('created_at', {
        header: () => <ColumnHeader label="categories.column.created" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {formatDate(row.original.created_at)}
            </span>
        ),
        meta: { hideBelow: 'lg' },
    }),
    column.accessor('creator', {
        header: () => <ColumnHeader label="categories.column.creator" />,
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {/* Null for a seeded row, or once the author has been removed. i18n-allow */}
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
        cell: ({ row }) => <CategoryActions category={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

export default function CategoriesIndex({ categories, filters }: Props) {
    const { t } = useTranslation();

    // setLayoutProps rather than a static `CategoriesIndex.layout`: a breadcrumb title
    // is a resolved string, and resolving one needs t(), which cannot run at module
    // scope. TenantLayout supplies the workspace crumb ahead of this one.
    setLayoutProps({
        breadcrumbs: [{ title: t('categories.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('categories.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('categories.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('categories.subtitle')}
                    </p>
                </div>
                <NewCategoryButton />
            </div>

            <DataTable
                href={index().url}
                page={categories}
                filters={filters}
                columns={columns}
                getRowId={(category) => String(category.id)}
                only={['categories']}
                searchPlaceholder={t('categories.search_placeholder')}
                noMatch={{
                    title: t('categories.no_match.title'),
                    description: t('categories.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Tags}
                        title={t('categories.empty.title')}
                        description={t('categories.empty.description')}
                        action={<NewCategoryButton />}
                    />
                }
            />
        </>
    );
}
