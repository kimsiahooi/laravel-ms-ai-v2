import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { WarehouseFormDialog } from '@/pages/warehouses/_components/warehouse-form-dialog';
import { destroy } from '@/routes/warehouses';

type Warehouse = App.Data.WarehouseData;

/**
 * What one row can do. The row owns both of its dialogs rather than reaching for page
 * state — see CategoryActions on why.
 */
export function WarehouseActions({ warehouse }: { warehouse: Warehouse }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(destroy({ warehouse: warehouse.id }).url);

    return (
        <>
            <RowActions
                name={warehouse.name}
                canEdit={can('warehouses.update')}
                canDelete={can('warehouses.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            />

            <WarehouseFormDialog
                open={editing}
                onOpenChange={setEditing}
                warehouse={warehouse}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('warehouses.confirm.delete_title', {
                    name: warehouse.name,
                })}
                description={t('warehouses.confirm.delete_description')}
                confirmLabel={t('warehouses.confirm.delete_submit')}
                busyLabel={t('warehouses.confirm.delete_submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
