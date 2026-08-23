import { router } from '@inertiajs/react';
import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { SupplierFormDialog } from '@/pages/suppliers/_components/supplier-form-dialog';
import { destroy } from '@/routes/suppliers';

type Supplier = App.Data.SupplierData;

/** What one row can do. The row owns its dialogs; see CategoryActions on why. */
export function SupplierActions({ supplier }: { supplier: Supplier }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <RowActions
                name={supplier.name}
                canEdit={can('suppliers.update')}
                canDelete={can('suppliers.delete')}
                onEdit={() => setEditing(true)}
                onDelete={() => setConfirming(true)}
            />

            <SupplierFormDialog
                open={editing}
                onOpenChange={setEditing}
                supplier={supplier}
            />

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('suppliers.confirm.delete_title', {
                    name: supplier.name,
                })}
                description={t('suppliers.confirm.delete_description')}
                confirmLabel={t('suppliers.confirm.delete_submit')}
                busyLabel={t('suppliers.confirm.delete_submitting')}
                variant="destructive"
                processing={processing}
                onConfirm={() => {
                    router.delete(destroy({ supplier: supplier.id }).url, {
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
