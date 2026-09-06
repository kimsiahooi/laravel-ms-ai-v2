import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cancel, post } from '@/routes/stock-takes';

/** The two ways a draft ends. Both are one-way, and both ask first. */
type Ending = 'post' | 'cancel';

/**
 * The end of the sheet: post the count, or throw it away.
 *
 * **Both endings live in one component, which is the whole point of it.** They share a
 * single in-flight flag, so while either request is running neither button can be
 * pressed — v1 disabled only the button that had been clicked, and posting a take while
 * its cancellation was in flight was a race anybody could win by being impatient. Two
 * components would have to lift that state into the page to say the same thing, and the
 * page would then be the only place the rule was written down.
 *
 * Both go through {@see ConfirmDialog} rather than firing on the press. Posting writes
 * to the ledger and cannot be undone; cancelling discards work somebody spent an
 * afternoon on. Neither is a click to be recovered from, so the count of what is about
 * to be applied goes in the post dialog's own words, where it is read at the moment it
 * matters rather than on the screen behind it.
 */
export function PostTakeDialog({ take }: { take: App.Data.StockTakeData }) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState<Ending | null>(null);
    const [busy, setBusy] = useState<Ending | null>(null);

    const send = (ending: Ending, url: string) => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                // onStart/onFinish rather than onSuccess: a refused post has to release
                // the buttons instead of leaving the sheet frozen behind a spinner.
                onStart: () => setBusy(ending),
                onFinish: () => {
                    setBusy(null);
                    setConfirming(null);
                },
            },
        );
    };

    // Either request holds both buttons. See above — this is the asymmetry being fixed.
    const working = busy !== null;

    return (
        <>
            <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:justify-end">
                <Button
                    variant="outline"
                    disabled={working}
                    onClick={() => setConfirming('cancel')}
                >
                    {t('stock-takes.action.cancel')}
                </Button>
                <Button
                    disabled={working}
                    onClick={() => setConfirming('post')}
                >
                    {t('stock-takes.action.post')}
                </Button>
            </div>

            <ConfirmDialog
                open={confirming === 'post'}
                onOpenChange={(open) => setConfirming(open ? 'post' : null)}
                title={t('stock-takes.dialog.post.title')}
                // The two numbers are the ones somebody needs before agreeing: an
                // uncounted line is skipped rather than applied, so "12 of 400" is a
                // sheet that is not finished, and this is the last place to notice.
                description={t('stock-takes.dialog.post.description', {
                    counted: take.counted_count,
                    total: take.line_count,
                })}
                confirmLabel={t('stock-takes.dialog.post.submit')}
                busyLabel={t('stock-takes.dialog.post.submitting')}
                processing={busy === 'post'}
                onConfirm={() => send('post', post({ stockTake: take.id }).url)}
            />

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={t('stock-takes.dialog.cancel.title')}
                description={t('stock-takes.dialog.cancel.description')}
                confirmLabel={t('stock-takes.dialog.cancel.submit')}
                busyLabel={t('stock-takes.dialog.cancel.submitting')}
                // Destructive, unlike posting: this one throws the counting away.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ stockTake: take.id }).url)
                }
            />
        </>
    );
}
