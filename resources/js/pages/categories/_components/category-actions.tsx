import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { CategoryFormDialog } from '@/pages/categories/_components/category-form-dialog';
import { destroy } from '@/routes/categories';

type Category = App.Data.CategoryData;

/**
 * What one row can do. The row owns both of its dialogs rather than reaching for
 * state on the page, which is what lets the column definitions stay at module
 * scope — TanStack treats the array as an input, and a cell that closed over page
 * state would force it to be rebuilt on every render.
 */
export function CategoryActions({ category }: { category: Category }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const remove = useResourceDelete(destroy({ category: category.id }).url);

    return (
        <>
            <RowActions
                name={category.name}
                canEdit={can('categories.update')}
                canDelete={can('categories.delete')}
                onEdit={() => setEditing(true)}
                onDelete={remove.ask}
            />

            <CategoryFormDialog
                open={editing}
                onOpenChange={setEditing}
                category={category}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('categories.confirm.delete_title', {
                    name: category.name,
                })}
                description={t('categories.confirm.delete_description')}
                confirmLabel={t('categories.confirm.delete_submit')}
                busyLabel={t('categories.confirm.delete_submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
