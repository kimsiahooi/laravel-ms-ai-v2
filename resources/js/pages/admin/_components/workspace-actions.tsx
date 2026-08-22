import { router } from '@inertiajs/react';
import { Archive, ExternalLink, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ConfirmDialog } from '@/pages/admin/_components/confirm-dialog';
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
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Actions for ${name}`}
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
                            Open workspace
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() => setConfirming(true)}
                    >
                        <Archive className="mr-2 size-4" />
                        Archive
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={`Archive ${name}?`}
                description="Everyone signed in to this workspace loses access, but nothing is deleted — its database is untouched and you can restore it from the Archive at any time."
                confirmLabel="Archive workspace"
                busyLabel="Archiving…"
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
