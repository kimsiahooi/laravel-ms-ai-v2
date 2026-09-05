import { Layers } from 'lucide-react';
import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { BomDialog } from '@/pages/products/_components/bom-dialog';
import { ProductFormDialog } from '@/pages/products/_components/product-form-dialog';
import { destroy } from '@/routes/products';

type Product = App.Data.ProductData;

/** What one row can do. The row owns its dialogs; see CategoryActions on why. */
export function ProductActions({ product }: { product: Product }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const [bomOpen, setBomOpen] = useState(false);
    const remove = useResourceDelete(destroy({ product: product.id }).url);

    return (
        <>
            <RowActions
                name={product.name}
                canEdit={can('products.update')}
                canDelete={can('products.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            >
                {/*
                    Gated on `products.update`, the same permission the route is: a bill
                    is an edit of the product, and TenantPermissions maps `products.bom`
                    to `products.update` for exactly that reason.
                */}
                {can('products.update') && (
                    <DropdownMenuItem onSelect={() => setBomOpen(true)}>
                        <Layers className="mr-2 size-4" />
                        {t('products.bom.action')}
                    </DropdownMenuItem>
                )}
            </RowActions>

            <ProductFormDialog
                open={editing}
                onOpenChange={setEditing}
                product={product}
            />

            <BomDialog
                open={bomOpen}
                onOpenChange={setBomOpen}
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
