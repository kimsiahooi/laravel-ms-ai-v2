import { router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/pages/admin/_components/confirm-dialog';
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
                    Restore
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Permanently delete ${name}`}
                    className="text-destructive hover:text-destructive"
                    onClick={() => setPending('delete')}
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>

            <ConfirmDialog
                open={pending === 'restore'}
                onOpenChange={(open) => !open && close()}
                title={`Restore ${name}?`}
                description="The workspace becomes reachable again at its original address, with all of its data as it was."
                confirmLabel="Restore workspace"
                busyLabel="Restoring…"
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
                title={`Permanently delete ${name}?`}
                description="This drops the workspace's database and everything in it. There is no undo and no backup."
                confirmLabel="Delete permanently"
                busyLabel="Deleting…"
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
