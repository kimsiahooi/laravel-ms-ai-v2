import { router } from '@inertiajs/react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, edit } from '@/routes/roles';

type Role = App.Data.RoleData;

/**
 * What one role's card can do: open the editor, or remove the role.
 *
 * **Nothing at all for the built-in Administrator.** It holds the whole catalog and is the
 * reason a workspace cannot lock itself out, so there is no version of editing or deleting it
 * that is safe. {@see \App\Http\Controllers\Tenant\RoleController} answers 403 to both
 * regardless of what this menu shows — a hidden item is still reachable by anybody willing to
 * craft a request. What the card shows instead is a sentence saying why.
 *
 * **Delete is withheld from a role somebody still holds**, and that is for the reader rather
 * than for safety: the server refuses it with a count, and the card is already showing that
 * count right above this menu. Offering a button that is going to be refused reads as a broken
 * button rather than as a rule.
 *
 * The editor is a page, so Edit navigates. `RowActions` reports the choice and each module
 * decides what it means — see that component on the split.
 */
export function RoleActions({ role }: { role: Role }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const remove = useResourceDelete(destroy({ role: role.id }).url);

    // After the hooks, never before: a conditional hook is a different component on the
    // next render.
    if (role.is_locked) {
        return null;
    }

    return (
        <>
            <RowActions
                name={role.name}
                canEdit={can('roles.update')}
                canDelete={can('roles.delete') && role.holders === 0}
                onEdit={() => router.visit(edit({ role: role.id }).url)}
                onDelete={remove.ask}
            />

            <ConfirmDialog
                open={remove.confirming}
                onOpenChange={remove.onOpenChange}
                title={t('roles.dialog.delete.title', { name: role.name })}
                description={t('roles.dialog.delete.description')}
                confirmLabel={t('roles.dialog.delete.submit')}
                busyLabel={t('roles.dialog.delete.submitting')}
                variant="destructive"
                processing={remove.processing}
                onConfirm={remove.confirm}
            />
        </>
    );
}
