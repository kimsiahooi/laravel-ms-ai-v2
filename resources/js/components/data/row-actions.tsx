import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    /** The row's own name, so the trigger says which row it belongs to. */
    name: string;
    canEdit: boolean;
    canDelete: boolean;
    onEdit: () => void;
    onDelete: () => void;
};

/**
 * The edit/delete menu at the end of a row.
 *
 * Only the menu. It reports what was chosen and the module decides what that means —
 * which dialog to open, which route to call, what the confirmation should say. That
 * split is deliberate: the markup here is identical everywhere, while the wording and
 * the consequences never are.
 *
 * `name` is for the trigger's accessible name alone. Twenty-five identical "Actions"
 * buttons are unusable with a screen reader; "Actions for Acme Steel" is not.
 *
 * The permissions hide what this user cannot do. They are never the boundary —
 * AuthorizeTenantRoute refuses the request itself, and a hidden menu item is still
 * reachable by anyone willing to craft one.
 */
export function RowActions({
    name,
    canEdit,
    canDelete,
    onEdit,
    onDelete,
}: Props) {
    const { t } = useTranslation();

    // A menu with nothing in it is a button that does nothing when clicked.
    if (!canEdit && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('common.actions.row_actions', { name })}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {canEdit && (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil className="mr-2 size-4" />
                        {t('common.actions.edit')}
                    </DropdownMenuItem>
                )}
                {canEdit && canDelete && <DropdownMenuSeparator />}
                {canDelete && (
                    <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                        <Trash2 className="mr-2 size-4" />
                        {t('common.actions.delete')}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
