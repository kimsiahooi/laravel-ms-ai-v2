import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { InlineLink } from '@/components/inline-link';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { RawMaterialFormDialog } from '@/pages/raw-materials/_components/raw-material-form-dialog';
import { index as products } from '@/routes/products';
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
    const canSeeProducts = can('products.view');
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
                >
                    {/*
                        The way out of the dialog. It names the products but the list
                        is capped at five, so with more than that the sentence ends in
                        an ellipsis and there is nowhere to read the rest — this is
                        where the rest is.

                        The destination is the products list filtered by this material,
                        which is the filter built for exactly this question. It cannot
                        land on nothing: the link only renders when a bill uses the
                        material, which is the same condition the filter matches on.

                        No link without the destination's view permission, the same
                        trade FilingLink makes — AuthorizeTenantRoute would 403 it, and
                        a link that 403s is worse than a sentence that stops.
                    */}
                    {canSeeProducts && (
                        <InlineLink
                            href={products(undefined, {
                                query: { material: String(rawMaterial.id) },
                            })}
                        >
                            {tChoice(
                                'raw-materials.confirm.blocked_link',
                                usedByCount,
                                { count: usedByCount },
                            )}
                        </InlineLink>
                    )}
                </ConfirmDialog>
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
