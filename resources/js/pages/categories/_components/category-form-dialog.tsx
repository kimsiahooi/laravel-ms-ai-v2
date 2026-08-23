import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { categorySchema } from '@/lib/validation/schemas/category';
import { store, update } from '@/routes/categories';

type Category = App.Data.CategoryData;

/**
 * Two fields and the words around them. The dialog, the submission, the gate and the
 * footer belong to {@see ResourceFormDialog}; the label/error/aria wiring belongs to
 * {@see TextField}.
 *
 * Create and edit are one component: the only difference is which route the form posts
 * to and which four strings it shows.
 */
export function CategoryFormDialog({
    open,
    onOpenChange,
    category,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the create form. */
    category?: Category;
}) {
    const editing = category !== undefined;

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={
                editing ? update.form({ category: category.id }) : store.form()
            }
            schema={categorySchema}
            title={
                editing ? 'categories.edit.title' : 'categories.create.title'
            }
            description={
                editing
                    ? 'categories.edit.description'
                    : 'categories.create.description'
            }
            submit={
                editing ? 'categories.edit.submit' : 'categories.create.submit'
            }
            submitting={
                editing
                    ? 'categories.edit.submitting'
                    : 'categories.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <TextField
                        name="name"
                        label="categories.field.name"
                        placeholder="categories.field.name_placeholder"
                        defaultValue={category?.name}
                        error={errors.name}
                        autoFocus
                    />

                    <TextField
                        name="description"
                        label="categories.field.description"
                        placeholder="categories.field.description_placeholder"
                        defaultValue={category?.description}
                        error={errors.description}
                        optional
                        rows={3}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
