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
import { useTranslation } from '@/hooks/use-translation';
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

/**
 * A sentinel we interpolate and then split on, so a React element can sit inside a
 * translated sentence without concatenating around it. Malay and Chinese put the
 * timestamp in a different place than English does — "Created :when" versus
 * "创建于 :when" is the easy case, but nothing guarantees the placeholder stays last.
 */
const PLACEHOLDER = '\u0000';

function NewestCreated({ iso, sentence }: { iso: string; sentence: string }) {
    const [before, after = ''] = sentence.split(PLACEHOLDER);

    return (
        <p className="text-muted-foreground text-sm">
            {before}
            <TimeAgo iso={iso} />
            {after}
        </p>
    );
}

export default function AdminDashboard({ stats, signups }: Props) {
    const { t } = useTranslation();

    return (
        <AdminLayout>
            <Head title={t('console.name')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('console.overview.heading')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('console.overview.subheading')}
                    </p>
                </div>
                <CreateWorkspaceSheet />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label={t('console.overview.stat_live')}
                    value={stats.total}
                    icon={Building2}
                />
                <StatCard
                    label={t('console.overview.stat_today')}
                    value={stats.added_today}
                    icon={CalendarPlus}
                />
                <StatCard
                    label={t('console.overview.stat_week')}
                    value={stats.last_7_days}
                    hint={t('console.overview.stat_week_hint')}
                    icon={TrendingUp}
                />
                <StatCard
                    label={t('console.overview.stat_archived')}
                    value={stats.archived}
                    hint={t('console.overview.stat_archived_hint')}
                    icon={Archive}
                />
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <SignupTrend days={signups} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('console.overview.newest_title')}
                        </CardTitle>
                        <CardDescription>
                            {t('console.overview.newest_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {stats.newest === null ? (
                            <p className="text-muted-foreground text-sm">
                                {t('console.overview.newest_empty')}
                            </p>
                        ) : (
                            <div className="space-y-1">
                                <p className="truncate font-medium">
                                    {stats.newest.name}
                                </p>
                                <p className="font-mono text-muted-foreground text-sm">
                                    /{stats.newest.slug}
                                </p>
                                <NewestCreated
                                    iso={stats.newest.created_at}
                                    sentence={t(
                                        'console.overview.newest_created',
                                        { when: PLACEHOLDER },
                                    )}
                                />
                            </div>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={index()}>
                                    {t('console.overview.link_all')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={trashed()}>
                                    {t('console.overview.link_archive')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
