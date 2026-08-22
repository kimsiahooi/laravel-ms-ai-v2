import { Head, Link } from '@inertiajs/react';
import { Archive, Building2, CalendarPlus, TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { CreateWorkspaceSheet } from '@/pages/admin/_components/create-workspace-sheet';
import type { SignupDay } from '@/pages/admin/_components/signup-trend';
import { SignupTrend } from '@/pages/admin/_components/signup-trend';
import { StatCard } from '@/pages/admin/_components/stat-card';
import { TimeAgo } from '@/pages/admin/_components/time-ago';
import { index, trashed } from '@/routes/admin/tenants';

type Props = {
    stats: {
        total: number;
        archived: number;
        added_today: number;
        last_7_days: number;
        newest: { name: string; slug: string; created_at: string } | null;
    };
    signups: SignupDay[];
};

export default function AdminDashboard({ stats, signups }: Props) {
    return (
        <AdminLayout>
            <Head title="Console" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        Overview
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        How the platform is being used.
                    </p>
                </div>
                <CreateWorkspaceSheet />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Live workspaces"
                    value={stats.total}
                    icon={Building2}
                />
                <StatCard
                    label="Added today"
                    value={stats.added_today}
                    icon={CalendarPlus}
                />
                <StatCard
                    label="Added this week"
                    value={stats.last_7_days}
                    hint="Rolling 7 days"
                    icon={TrendingUp}
                />
                <StatCard
                    label="Archived"
                    value={stats.archived}
                    hint="Restorable from the archive"
                    icon={Archive}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <SignupTrend days={signups} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Most recent</CardTitle>
                        <CardDescription>
                            The last workspace to be created.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {stats.newest === null ? (
                            <p className="text-muted-foreground text-sm">
                                No workspaces yet.
                            </p>
                        ) : (
                            <div className="space-y-1">
                                <p className="truncate font-medium">
                                    {stats.newest.name}
                                </p>
                                <p className="font-mono text-muted-foreground text-sm">
                                    /{stats.newest.slug}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    Created{' '}
                                    <TimeAgo iso={stats.newest.created_at} />
                                </p>
                            </div>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={index()}>All workspaces</Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={trashed()}>Archive</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
