import { Head, Link } from '@inertiajs/react';
import { Archive, SearchX } from 'lucide-react';
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
import { ArchivedWorkspaceActions } from '@/pages/admin/_components/archived-workspace-actions';
import { EmptyState } from '@/pages/admin/_components/empty-state';
import { ListToolbar } from '@/pages/admin/_components/list-toolbar';
import { PaginationBar } from '@/pages/admin/_components/pagination-bar';
import { TimeAgo } from '@/pages/admin/_components/time-ago';
import { index, trashed } from '@/routes/admin/tenants';
import type { Paginated } from '@/types';

type ArchivedWorkspace = {
    slug: string;
    name: string;
    deleted_at: string | null;
};

type Props = {
    tenants: Paginated<ArchivedWorkspace>;
    filters: { search: string; per_page: number };
};

export default function TenantsTrashed({ tenants, filters }: Props) {
    const { t } = useTranslation();
    const href = trashed().url;
    const isSearching = filters.search !== '';

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

            <Card className="gap-0 overflow-hidden py-0">
                <div className="p-4">
                    <ListToolbar
                        href={href}
                        search={filters.search}
                        perPage={filters.per_page}
                        placeholder={t('console.archive.search_placeholder')}
                    />
                </div>

                {tenants.data.length === 0 ? (
                    isSearching ? (
                        <EmptyState
                            icon={SearchX}
                            title={t('console.archive.no_match_title')}
                            description={t(
                                'console.archive.no_match_description',
                                {
                                    term: filters.search,
                                },
                            )}
                        />
                    ) : (
                        <EmptyState
                            icon={Archive}
                            title={t('console.archive.empty_title')}
                            description={t('console.archive.empty_description')}
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
                                    {t('console.archive.column_archived')}
                                </TableHead>
                                <TableHead className="pr-4 text-right">
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
                                        <TimeAgo iso={workspace.deleted_at} />
                                    </TableCell>
                                    <TableCell className="pr-4 text-right">
                                        <ArchivedWorkspaceActions
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
