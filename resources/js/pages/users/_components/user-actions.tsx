import { router } from '@inertiajs/react';
import { RotateCcw, UserMinus } from 'lucide-react';
import { useState } from 'react';
import { RowActions } from '@/components/data/row-actions';
import { ConfirmDialog } from '@/components/feedback/confirm-dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/hooks/use-permissions';
import { useResourceDelete } from '@/hooks/use-resource-delete';
import { useTranslation } from '@/hooks/use-translation';
import { UserFormDialog } from '@/pages/users/_components/user-form-dialog';
import { destroy, restore } from '@/routes/users';

type User = App.Data.UserData;

/**
 * What one person's row can do: edit them, and switch off or back on their ability to sign in.
 *
 * **Deactivate is written as a `children` item rather than `RowActions`' own Delete**, because
 * that one is labelled "Delete" and this is not a delete. The row survives — orders name the
 * person who raised them — and what an administrator wants is for somebody to stop signing in.
 * A menu that said "Delete" and left the row would be lying about which of the two happened.
 *
 * **Three things are hidden here and none of them is the guard.** You cannot deactivate
 * yourself, and you cannot deactivate the last administrator; both are refused by
 * {@see \App\Actions\DeactivateUser} whatever this menu shows, because a hidden item is still
 * reachable by anybody willing to craft a request. Hiding them is so the menu does not offer
 * an action that is going to be refused — which reads as a broken button rather than a rule.
 */
export function UserActions({ user }: { user: User }) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editing, setEditing] = useState(false);
    const deactivate = useResourceDelete(destroy({ user: user.id }).url);

    const deactivated = user.deleted_at !== null;

    // Refused by the server in both cases — see the component note. This only decides
    // whether the menu offers it.
    const canDeactivate =
        can('users.delete') &&
        !deactivated &&
        !user.is_self &&
        !user.is_last_administrator;

    return (
        <>
            <RowActions
                name={user.name}
                canEdit={can('users.update') && !deactivated}
                // Never the built-in Delete: see the note.
                canDelete={false}
                onEdit={() => setEditing(true)}
                onDelete={() => undefined}
            >
                {canDeactivate && (
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={deactivate.ask}
                    >
                        <UserMinus className="mr-2 size-4" />
                        {t('users.action.deactivate')}
                    </DropdownMenuItem>
                )}

                {deactivated && can('users.update') && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.patch(
                                restore({ user: user.id }).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RotateCcw className="mr-2 size-4" />
                        {t('users.action.restore')}
                    </DropdownMenuItem>
                )}
            </RowActions>

            <UserFormDialog
                open={editing}
                onOpenChange={setEditing}
                user={user}
            />

            <ConfirmDialog
                open={deactivate.confirming}
                onOpenChange={deactivate.onOpenChange}
                title={t('users.dialog.deactivate.title', { name: user.name })}
                description={t('users.dialog.deactivate.description')}
                confirmLabel={t('users.dialog.deactivate.submit')}
                busyLabel={t('users.dialog.deactivate.submitting')}
                variant="destructive"
                processing={deactivate.processing}
                onConfirm={deactivate.confirm}
            />
        </>
    );
}
