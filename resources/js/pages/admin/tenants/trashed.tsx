import { Head, Link } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin-layout';
import { ArchivedWorkspaceActions } from '@/pages/admin/_components/archived-workspace-actions';
import { TimeAgo } from '@/pages/admin/_components/time-ago';
import { index, trashed } from '@/routes/admin/tenants';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\ArchivedTenantData; see the note in the sibling index page. */
type ArchivedWorkspace = App.Data.ArchivedTenantData;

type Props = {
    tenants: Paginated<ArchivedWorkspace>;
    filters: ResourceFilters;
};

/** Module scope: see the note in the sibling index page. */
const column = columnsFor<ArchivedWorkspace>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('console.workspaces.column_workspace', {
            width: 'max-w-[16rem] truncate',
        }),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                <span className="block text-muted-foreground text-xs sm:hidden">
                    /{row.original.slug}
                </span>
            </>
        ),
    }),
    column.accessor('slug', {
        id: 'id',
        ...heading('console.workspaces.column_address', { hideBelow: 'sm' }),
        cell: ({ row }) => (
            <Badge variant="secondary" className="font-mono font-normal">
                /{row.original.slug}
            </Badge>
        ),
    }),
    column.accessor('deleted_at', {
        ...heading('console.archive.column_archived', { hideBelow: 'md' }),
        cell: ({ row }) => <TimeAgo iso={row.original.deleted_at} />,
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => (
            <ArchivedWorkspaceActions
                slug={row.original.slug}
                name={row.original.name}
            />
        ),
        // Two inline buttons, not a dropdown — wider than the live list's actions.
        meta: { align: 'end', width: 'w-40' },
    }),
]);

export default function TenantsTrashed({ tenants, filters }: Props) {
    const { t } = useTranslation();

    return (
        <AdminLayout
            breadcrumbs={[
                { title: t('console.workspaces.heading'), href: index() },
                { title: t('console.archive.heading'), href: trashed() },
            ]}
        >
            <Head title={t('console.archive.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('console.archive.heading')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('console.archive.subheading')}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={index()}>{t('console.archive.back')}</Link>
                </Button>
            </div>

            <DataTable
                href={trashed().url}
                tableKey="admin-tenants-trashed"
                page={tenants}
                filters={filters}
                columns={columns}
                getRowId={(workspace) => workspace.slug}
                only={['tenants']}
                searchPlaceholder={t('console.archive.search_placeholder')}
                noMatch={{
                    title: t('console.archive.no_match_title'),
                    description: t('console.archive.no_match_description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Archive}
                        title={t('console.archive.empty_title')}
                        description={t('console.archive.empty_description')}
                    />
                }
            />
        </AdminLayout>
    );
}
