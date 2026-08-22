import { router } from '@inertiajs/react';
import { Archive, ExternalLink, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { destroy } from '@/routes/admin/tenants';

/**
 * Row actions for a live workspace. Archiving is a soft delete — the workspace's
 * database is left completely alone — so the wording promises exactly that, and the
 * irreversible option lives on the Archive screen instead.
 */
export function WorkspaceActions({
    slug,
    name,
}: {
    slug: string;
    name: string;
}) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.actions.row_actions', { name })}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem asChild>
                        {/* A full page load, not an Inertia visit: the workspace runs
                            on its own session and its own database. */}
                        <a href={`/${slug}`}>
                            <ExternalLink className="mr-2 size-4" />
                            {t('console.row.open')}
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setConfirming(true)}
                    >
                        <Archive className="mr-2 size-4" />
                        {t('console.row.archive')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('console.confirm.archive_title', { name })}
                description={t('console.confirm.archive_description')}
                confirmLabel={t('console.confirm.archive_submit')}
                busyLabel={t('console.confirm.archive_submitting')}
                variant="destructive"
                processing={processing}
                onConfirm={() => {
                    router.delete(destroy(slug).url, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setConfirming(false);
                        },
                    });
                }}
            />
        </>
    );
}
