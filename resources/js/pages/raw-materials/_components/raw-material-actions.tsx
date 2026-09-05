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
    const { t, tChoice } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(
        destroy({ rawMaterial: rawMaterial.id }).url,
    );

    const usedByCount = rawMaterial.bom_product_count;
    // The names are capped by the DTO, so say so when the list is short of the count.
    // An ellipsis rather than "and N more": it is punctuation, and the sentence around
    // it already carries the real total.
    const usedByNames =
        rawMaterial.bom_products.join(', ') +
        (usedByCount > rawMaterial.bom_products.length ? '…' : '');

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

            {/*
                A material a product is built from cannot be deleted — `bom_items`
                requires it, so removing it would leave a bill nobody can render or
                save. The controller refuses either way; this is what makes Delete
                explain itself instead of failing.
            */}
            {usedByCount > 0 ? (
                <ConfirmDialog
                    blocked
                    open={remove.confirming}
                    onOpenChange={remove.onOpenChange}
                    title={t('raw-materials.confirm.blocked_title', {
                        name: rawMaterial.name,
                    })}
                    description={tChoice(
                        'raw-materials.confirm.blocked_description',
                        usedByCount,
                        { products: usedByNames },
                    )}
                />
            ) : (
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
            )}
        </>
    );
}
