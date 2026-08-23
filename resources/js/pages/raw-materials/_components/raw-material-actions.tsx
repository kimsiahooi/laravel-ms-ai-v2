import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { RawMaterialFormDialog } from '@/pages/raw-materials/_components/raw-material-form-dialog';
import { destroy } from '@/routes/raw-materials';

type RawMaterial = App.Data.RawMaterialData;

/** What one row can do. The row owns its dialogs; see CategoryActions on why. */
export function RawMaterialActions({
    rawMaterial,
}: {
    rawMaterial: RawMaterial;
}) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(
        destroy({ rawMaterial: rawMaterial.id }).url,
    );

    return (
        <>
            <RowActions
                name={rawMaterial.name}
                canEdit={can('raw-materials.update')}
                canDelete={can('raw-materials.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            />

            <RawMaterialFormDialog
                open={editing}
                onOpenChange={setEditing}
                rawMaterial={rawMaterial}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('raw-materials.confirm.delete_title', {
                    name: rawMaterial.name,
                })}
                description={t('raw-materials.confirm.delete_description')}
                confirmLabel={t('raw-materials.confirm.delete_submit')}
                busyLabel={t('raw-materials.confirm.delete_submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
