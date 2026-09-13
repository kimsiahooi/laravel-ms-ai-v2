import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { cancel } from '@/routes/sales-orders';

type Order = App.Data.SalesOrderData;

/**
 * The end of the order — for now, only one of the two.
 *
 * **Fulfilment is not here yet**, and this component is deliberately shaped so that adding
 * it is an addition rather than a rewrite: the purchase side's equivalent holds both of its
 * endings in one component precisely so they can share a single in-flight flag, because
 * shipping an order while its cancellation is in flight is a race anybody wins by being
 * impatient. `busy` is already a nullable *ending* rather than a boolean for that reason,
 * even though there is currently one value it can take.
 *
 * **Cancelling is not undoable and the dialog says so.** It closes a document a customer may
 * already have been sent.
 *
 * Renders nothing on an order that has already ended, and nothing without the permission —
 * the hooks run first regardless, because a conditional hook is a different component on the
 * next render.
 */
export function OrderActions({ order }: { order: Order }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState<'cancel' | null>(null);

    const send = (ending: 'cancel', url: string) => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                // onStart/onFinish rather than onSuccess: a refused request has to release the
                // button instead of leaving the document frozen behind a spinner.
                onStart: () => setBusy(ending),
                onFinish: () => {
                    setBusy(null);
                    setConfirming(false);
                },
            },
        );
    };

    if (!can('sales-orders.update') || order.status !== 'pending') {
        return null;
    }

    return (
        <Card>
            <CardContent>
                <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <Button
                        variant="outline"
                        disabled={busy !== null}
                        onClick={() => setConfirming(true)}
                    >
                        {t('sales-orders.action.cancel')}
                    </Button>
                </div>
            </CardContent>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('sales-orders.dialog.cancel.title')}
                description={t('sales-orders.dialog.cancel.description')}
                confirmLabel={t('sales-orders.dialog.cancel.submit')}
                busyLabel={t('sales-orders.dialog.cancel.submitting')}
                // Destructive: this closes the order for good.
                variant="destructive"
                processing={busy === 'cancel'}
                onConfirm={() =>
                    send('cancel', cancel({ salesOrder: order.id }).url)
                }
            />
        </Card>
    );
}
