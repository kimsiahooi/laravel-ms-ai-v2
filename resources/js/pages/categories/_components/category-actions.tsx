import { router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { CategoryFormDialog } from '@/pages/categories/_components/category-form-dialog';
import { destroy } from '@/routes/categories';

type Category = App.Data.CategoryData;

/**
 * What one row can do. The row owns both of its dialogs rather than reaching for
 * state on the page, which is what lets the column definitions stay at module
 * scope — TanStack treats the array as an input, and a cell that closed over page
 * state would force it to be rebuilt on every render.
 *
 * `can()` hides what this user may not do. It is not the boundary: every route is
 * checked again by AuthorizeTenantRoute, and a hidden menu item is still reachable
 * by anyone willing to craft the request.
 */
export function CategoryActions({ category }: { category: Category }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const canEdit = can('categories.update');
    const canDelete = can('categories.delete');

    // A menu with nothing in it is a button that does nothing when clicked.
    if (!canEdit && !canDelete) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.actions.row_actions', {
                            name: category.name,
                        })}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                    {canEdit && (
                        <DropdownMenuItem onSelect={() => setEditing(true)}>
                            <Pencil className="mr-2 size-4" />
                            {t('common.actions.edit')}
                        </DropdownMenuItem>
                    )}
                    {canEdit && canDelete && <DropdownMenuSeparator />}
                    {canDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => setConfirming(true)}
                        >
                            <Trash2 className="mr-2 size-4" />
                            {t('common.actions.delete')}
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <CategoryFormDialog
                open={editing}
                onOpenChange={setEditing}
                category={category}
            />

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('categories.confirm.delete_title', {
                    name: category.name,
                })}
                description={t('categories.confirm.delete_description')}
                confirmLabel={t('categories.confirm.delete_submit')}
                busyLabel={t('categories.confirm.delete_submitting')}
                variant="destructive"
                processing={processing}
                onConfirm={() => {
                    router.delete(destroy({ category: category.id }).url, {
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
