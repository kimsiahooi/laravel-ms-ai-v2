import { router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { forceDestroy, restore } from '@/routes/admin/tenants';

type Pending = 'restore' | 'delete' | null;

/**
 * Row actions for an archived workspace.
 *
 * Permanent deletion drops the workspace's entire database — every order, every
 * stock movement, gone with no backup taken by this app. It is the one action in the
 * console with no undo, so it asks for the slug to be typed out first.
 */
export function ArchivedWorkspaceActions({
    slug,
    name,
}: {
    slug: string;
    name: string;
}) {
    const { t } = useTranslation();
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setProcessing(false);
        setPending(null);
    };

    return (
        <>
            <div className="flex items-center justify-end gap-1">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPending('restore')}
                >
                    <RotateCcw className="size-4" />
                    {t('console.row.restore')}
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('console.row.delete_forever', { name })}
                    className="text-destructive hover:text-destructive"
                    onClick={() => setPending('delete')}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            <ConfirmDialog
                open={pending === 'restore'}
                onOpenChange={(open) => !open && close()}
                title={t('console.confirm.restore_title', { name })}
                description={t('console.confirm.restore_description')}
                confirmLabel={t('console.confirm.restore_submit')}
                busyLabel={t('console.confirm.restore_submitting')}
                processing={processing}
                onConfirm={() => {
                    router.patch(
                        restore(slug).url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: close,
                        },
                    );
                }}
            />

            <ConfirmDialog
                open={pending === 'delete'}
                onOpenChange={(open) => !open && close()}
                title={t('console.confirm.delete_title', { name })}
                description={t('console.confirm.delete_description')}
                confirmLabel={t('console.confirm.delete_submit')}
                busyLabel={t('console.confirm.delete_submitting')}
                variant="destructive"
                confirmPhrase={slug}
                processing={processing}
                onConfirm={() => {
                    router.delete(forceDestroy(slug).url, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: close,
                    });
                }}
            />
        </>
    );
}
