import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { destroy } from '@/routes/tenant/table-columns';

/**
 * Put every list back to the columns it declares.
 *
 * The counterpart to the Reset inside each list's own Columns panel, which only knows
 * about the list it is open on. Somebody who has rearranged six screens and wants to
 * start over would otherwise have to visit six screens to do it.
 *
 * **The count comes from the shared prop, not a request.** `tableColumns` already rides
 * on every page so the table can seed itself, and only lists somebody actually changed
 * are ever stored — so its size *is* the number of customised lists, for free.
 *
 * Asks before it acts. One press can undo work across ten screens, which is more than a
 * settings toggle should do quietly; no typed phrase though, since nothing here is
 * irreversible and asking on every reset would only teach people to click through.
 */
export function ColumnResetCard() {
    const { t, tChoice } = useTranslation();
    const { tableColumns } = usePage().props;
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const customised = Object.keys(tableColumns ?? {}).length;

    return (
        <div className="space-y-4">
            <div className="space-y-0.5">
                <h2 className="font-medium text-base">
                    {t('settings.columns.title')}
                </h2>
                <p className="text-muted-foreground text-sm">
                    {t('settings.columns.description')}
                </p>
            </div>

            <p className="text-sm">
                {tChoice('settings.columns.customised', customised, {
                    count: customised,
                })}
            </p>

            <Button
                type="button"
                variant="outline"
                // Nothing to undo is a button that would do nothing. The sentence above
                // already says why it is not available, so it needs no second copy.
                disabled={customised === 0}
                onClick={() => setConfirming(true)}
            >
                {t('settings.columns.action')}
            </Button>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('settings.columns.confirm_title')}
                description={t('settings.columns.confirm_description')}
                confirmLabel={t('settings.columns.confirm_action')}
                busyLabel={t('settings.columns.confirming')}
                variant="destructive"
                processing={processing}
                onConfirm={() =>
                    router.delete(destroy().url, {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => {
                            setProcessing(false);
                            setConfirming(false);
                        },
                    })
                }
            />
        </div>
    );
}
