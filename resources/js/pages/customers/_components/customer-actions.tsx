import { router } from '@inertiajs/react';
import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CustomerFormDialog } from '@/pages/customers/_components/customer-form-dialog';
import { destroy } from '@/routes/customers';

type Customer = App.Data.CustomerData;

/** What one row can do. The row owns its dialogs; see CategoryActions on why. */
export function CustomerActions({ customer }: { customer: Customer }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <RowActions
                name={customer.name}
                canEdit={can('customers.update')}
                canDelete={can('customers.delete')}
                onEdit={() => setEditing(true)}
                onDelete={() => setConfirming(true)}
            />

            <CustomerFormDialog
                open={editing}
                onOpenChange={setEditing}
                customer={customer}
            />

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('customers.confirm.delete_title', {
                    name: customer.name,
                })}
                description={t('customers.confirm.delete_description')}
                confirmLabel={t('customers.confirm.delete_submit')}
                busyLabel={t('customers.confirm.delete_submitting')}
                variant="destructive"
                processing={processing}
                onConfirm={() => {
                    router.delete(destroy({ customer: customer.id }).url, {
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
