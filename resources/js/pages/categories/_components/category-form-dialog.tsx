import { Form } from '@inertiajs/react';
import { useId } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { useZodGate } from '@/hooks/use-zod-gate';
import { categorySchema } from '@/lib/validation/schemas/category';
import { store, update } from '@/routes/categories';

type Category = App.Data.CategoryData;

/**
 * The create/edit form. One component for both, because the fields, the rules and the
 * validation are identical — only the route and the wording differ, and splitting them
 * would mean keeping two copies of the fields in step by hand.
 *
 * The inputs are uncontrolled, seeded from `category`. Radix unmounts the dialog's
 * content when it closes, so they take their values with them: an abandoned edit cannot
 * come back on the next open, and no reset is needed. That is also why the caller must
 * keep `category` set while the dialog closes — clearing it early would swap the copy
 * to "New category" mid-animation.
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
    const { t } = useTranslation();
    const gate = useZodGate(categorySchema);
    // Ids rather than fixed strings: a row's dropdown owns its own copy of this
    // dialog, so fixed ids would collide the moment two were mounted at once.
    const nameId = useId();
    const descriptionId = useId();
    const editing = category !== undefined;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? t('categories.edit.title')
                            : t('categories.create.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {editing
                            ? t('categories.edit.description')
                            : t('categories.create.description')}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...(editing
                        ? update.form({ category: category.id })
                        : store.form())}
                    {...gate}
                    noValidate
                    disableWhileProcessing
                    // The write returns back(), so the list re-renders in place; without
                    // this the page would jump to the top on every save.
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor={nameId}>
                                    {t('categories.field.name')}
                                </Label>
                                <Input
                                    id={nameId}
                                    name="name"
                                    defaultValue={category?.name ?? ''}
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder={t(
                                        'categories.field.name_placeholder',
                                    )}
                                    aria-invalid={!!errors.name}
                                    aria-describedby={
                                        errors.name
                                            ? `${nameId}-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${nameId}-error`}
                                    role="alert"
                                    message={errors.name}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={descriptionId}>
                                    {t('categories.field.description')}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        {t('common.field.optional')}
                                    </span>
                                </Label>
                                <Textarea
                                    id={descriptionId}
                                    name="description"
                                    defaultValue={category?.description ?? ''}
                                    rows={3}
                                    placeholder={t(
                                        'categories.field.description_placeholder',
                                    )}
                                    aria-invalid={!!errors.description}
                                    aria-describedby={
                                        errors.description
                                            ? `${descriptionId}-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${descriptionId}-error`}
                                    role="alert"
                                    message={errors.description}
                                />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        {t('common.actions.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit">
                                    {processing && <Spinner />}
                                    {processing
                                        ? t(
                                              editing
                                                  ? 'categories.edit.submitting'
                                                  : 'categories.create.submitting',
                                          )
                                        : t(
                                              editing
                                                  ? 'categories.edit.submit'
                                                  : 'categories.create.submit',
                                          )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
