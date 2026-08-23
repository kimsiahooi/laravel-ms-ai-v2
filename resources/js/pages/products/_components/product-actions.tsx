import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { ProductFormDialog } from '@/pages/products/_components/product-form-dialog';
import { destroy } from '@/routes/products';

type Product = App.Data.ProductData;

/** What one row can do. The row owns its dialogs; see CategoryActions on why. */
export function ProductActions({ product }: { product: Product }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(destroy({ product: product.id }).url);

    return (
        <>
            <RowActions
                name={product.name}
                canEdit={can('products.update')}
                canDelete={can('products.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            />

            <ProductFormDialog
                open={editing}
                onOpenChange={setEditing}
                product={product}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('products.confirm.delete_title', {
                    name: product.name,
                })}
                description={t('products.confirm.delete_description')}
                confirmLabel={t('products.confirm.delete_submit')}
                busyLabel={t('products.confirm.delete_submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
