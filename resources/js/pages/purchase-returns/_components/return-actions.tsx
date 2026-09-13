import { Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, edit } from '@/routes/purchase-returns';

type Return = App.Data.PurchaseReturnData;

/**
 * What can still be done to a return: change it, or remove it.
 *
 * **Both only while it is pending**, and the server draws the same line — an update or a
 * delete against a completed return is refused there, not merely hidden here.
 *
 * **Deleting is not a reversal and the dialog says so.** In this slice nothing has moved yet,
 * so removing a return simply releases the quantities it was holding and there is nothing to
 * put back. Once returns can be completed, a completed one stops being deletable entirely —
 * it is the source of ledger rows, and those must not name a document nobody can open.
 *
 * The hooks run before the early return, because a conditional hook is a different component
 * on the next render.
 */
export function ReturnActions({ row }: { row: Return }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const remove = useResourceDelete(destroy({ purchaseReturn: row.id }).url);

    if (row.status !== 'pending') {
        return null;
    }

    return (
        <>
            <div className="flex flex-wrap gap-3">
                {can('purchase-returns.update') && (
                    <Button variant="outline" asChild>
                        <Link href={edit({ purchaseReturn: row.id })}>
                            <Pencil className="size-4" />
                            {t('purchase-returns.action.edit')}
                        </Link>
                    </Button>
                )}

                {can('purchase-returns.delete') && (
                    <Button variant="outline" onClick={remove.ask}>
                        <Trash2 className="size-4" />
                        {t('common.actions.delete')}
                    </Button>
                )}
            </div>

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('purchase-returns.dialog.delete.title', {
                    number: row.number,
                })}
                description={t('purchase-returns.dialog.delete.description')}
                confirmLabel={t('purchase-returns.dialog.delete.submit')}
                busyLabel={t('purchase-returns.dialog.delete.submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
