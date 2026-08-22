import { Head, Link } from '@inertiajs/react';
import { Building2, SearchX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import AdminLayout from '@/layouts/admin-layout';
import { CreateWorkspaceSheet } from '@/pages/admin/_components/create-workspace-sheet';
import { EmptyState } from '@/pages/admin/_components/empty-state';
import { ListToolbar } from '@/pages/admin/_components/list-toolbar';
import { PaginationBar } from '@/pages/admin/_components/pagination-bar';
import { TimeAgo } from '@/pages/admin/_components/time-ago';
import { WorkspaceActions } from '@/pages/admin/_components/workspace-actions';
import { index, trashed } from '@/routes/admin/tenants';
import type { Paginated } from '@/types';

type Workspace = {
    slug: string;
    name: string;
    created_at: string;
};

type Props = {
    tenants: Paginated<Workspace>;
    filters: { search: string; per_page: number };
};

export default function TenantsIndex({ tenants, filters }: Props) {
    const { t } = useTranslation();
    const href = index().url;
    const isSearching = filters.search !== '';

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

            <Card className="gap-0 overflow-hidden py-0">
                <div className="p-4">
                    <ListToolbar
                        href={href}
                        search={filters.search}
                        perPage={filters.per_page}
                        placeholder={t('console.workspaces.search_placeholder')}
                    />
                </div>

                {tenants.data.length === 0 ? (
                    isSearching ? (
                        <EmptyState
                            icon={SearchX}
                            title={t('console.workspaces.no_match_title')}
                            description={t(
                                'console.workspaces.no_match_description',
                                {
                                    term: filters.search,
                                },
                            )}
                        />
                    ) : (
                        <EmptyState
                            icon={Building2}
                            title={t('console.workspaces.empty_title')}
                            description={t(
                                'console.workspaces.empty_description',
                            )}
                            action={<CreateWorkspaceSheet />}
                        />
                    )
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="pl-4">
                                    {t('console.workspaces.column_workspace')}
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    {t('console.workspaces.column_address')}
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    {t('console.workspaces.column_created')}
                                </TableHead>
                                <TableHead className="w-12 pr-4 text-right">
                                    <span className="sr-only">
                                        {t('common.list.actions_column')}
                                    </span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tenants.data.map((workspace) => (
                                <TableRow key={workspace.slug}>
                                    <TableCell className="max-w-[16rem] truncate pl-4 font-medium">
                                        {workspace.name}
                                        <span className="block text-muted-foreground text-xs sm:hidden">
                                            /{workspace.slug}
                                        </span>
                                    </TableCell>
                                    <TableCell className="hidden sm:table-cell">
                                        <Badge
                                            variant="secondary"
                                            className="font-mono font-normal"
                                        >
                                            /{workspace.slug}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        <TimeAgo iso={workspace.created_at} />
                                    </TableCell>
                                    <TableCell className="pr-4 text-right">
                                        <WorkspaceActions
                                            slug={workspace.slug}
                                            name={workspace.name}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <PaginationBar
                    href={href}
                    page={tenants}
                    params={{
                        search: filters.search || undefined,
                        per_page: filters.per_page,
                    }}
                />
            </Card>
        </AdminLayout>
    );
}
