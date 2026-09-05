import { Head, Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin-layout';
import { CreateWorkspaceSheet } from '@/pages/admin/_components/create-workspace-sheet';
import { TimeAgo } from '@/pages/admin/_components/time-ago';
import { WorkspaceActions } from '@/pages/admin/_components/workspace-actions';
import { index, trashed } from '@/routes/admin/tenants';
import type { Paginated, ResourceFilters } from '@/types';

/**
 * The row shape, generated from App\Data\TenantData — see `bun run types:generate`.
 * Aliased rather than used inline because the alias is what every column and cell
 * below is written against, and because `App.Data.*` arrives as an ambient global
 * rather than through the `@/types` barrel.
 */
type Workspace = App.Data.TenantData;

type Props = {
    tenants: Paginated<Workspace>;
    filters: ResourceFilters;
};

/**
 * Columns are built once at module scope, not per render: TanStack treats the array
 * as an input, and a fresh one each render rebuilds every column instance.
 *
 * A column's id is the column the server sorts by — `name`, `id` (the slug),
 * `created_at`. Which of them are actually clickable is decided by the controller's
 * allow-list, which arrives in `filters.sortable`; nothing here declares it.
 */
const column = columnsFor<Workspace>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('console.workspaces.column_workspace', {
            width: 'max-w-[16rem] truncate',
        }),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {/* The address has no column of its own on a phone, so it rides along. */}
                <span className="block text-muted-foreground text-xs sm:hidden">
                    /{row.original.slug}
                </span>
            </>
        ),
    }),
    column.accessor('slug', {
        // `id` is the primary key on Tenant, so that is the name the server sorts by.
        id: 'id',
        ...heading('console.workspaces.column_address', { hideBelow: 'sm' }),
        cell: ({ row }) => (
            <Badge variant="secondary" className="font-mono font-normal">
                /{row.original.slug}
            </Badge>
        ),
    }),
    column.accessor('created_at', {
        ...heading('console.workspaces.column_created', { hideBelow: 'md' }),
        cell: ({ row }) => <TimeAgo iso={row.original.created_at} />,
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => (
            <WorkspaceActions
                slug={row.original.slug}
                name={row.original.name}
            />
        ),
        meta: { align: 'end', width: 'w-12' },
    }),
]);

export default function TenantsIndex({ tenants, filters }: Props) {
    const { t } = useTranslation();

    return (
        <AdminLayout
            breadcrumbs={[
                { title: t('console.workspaces.heading'), href: index() },
            ]}
        >
            <Head title={t('console.workspaces.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('console.workspaces.heading')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('console.workspaces.subheading')}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={trashed()}>
                            {t('console.workspaces.view_archive')}
                        </Link>
                    </Button>
                    <CreateWorkspaceSheet />
                </div>
            </div>

            <DataTable
                href={index().url}
                page={tenants}
                filters={filters}
                columns={columns}
                getRowId={(workspace) => workspace.slug}
                only={['tenants']}
                searchPlaceholder={t('console.workspaces.search_placeholder')}
                noMatch={{
                    title: t('console.workspaces.no_match_title'),
                    description: t('console.workspaces.no_match_description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Building2}
                        title={t('console.workspaces.empty_title')}
                        description={t('console.workspaces.empty_description')}
                        action={<CreateWorkspaceSheet />}
                    />
                }
            />
        </AdminLayout>
    );
}
