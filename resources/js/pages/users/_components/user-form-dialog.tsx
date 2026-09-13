import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { ComboboxField } from '@/components/form/combobox-field';
import { ResourceFormDialog } from '@/components/form/resource-form-dialog';
import { TextField } from '@/components/form/text-field';
import { userSchema } from '@/lib/validation/schemas/user';
import { store, update } from '@/routes/users';

type User = App.Data.UserData;
type PageProps = { roles: App.Data.OptionData[] };

/**
 * Adding a colleague, and editing one. Five boxes.
 *
 * **The password is typed here by an administrator**, which is the whole reason this form
 * looks the way it does. The alternative — emailing a set-password link — would have no
 * password field at all and no confirmation; this app cannot take that route because a
 * workspace has to be able to onboard somebody where SMTP is not configured. The cost is
 * that one person briefly knows another's password, and the hint under the box says so
 * rather than leaving it to be inferred.
 *
 * **On an edit the password is optional and the hint changes to say what blank means.**
 * An empty box is the common case there — somebody fixing a typo in a name — and the two
 * hints are the difference between "leave this alone" and "you must fill this in".
 *
 * `autoComplete="new-password"` on both boxes, so the browser does not helpfully offer the
 * *administrator's* own saved password for the account they are creating for somebody else.
 */
export function UserFormDialog({
    open,
    onOpenChange,
    user,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The row being edited. Absent means this is the add form. */
    user?: User;
}) {
    const { roles } = usePage<PageProps>().props;
    const editing = user !== undefined;

    // Memoised on the ids, so the schema is one value for the life of the page rather than
    // a new one each render — see the note on userSchema.
    const ids = useMemo(() => roles.map((role) => role.id), [roles]);
    const schema = useMemo(() => userSchema(ids, editing), [ids, editing]);

    return (
        <ResourceFormDialog
            open={open}
            onOpenChange={onOpenChange}
            action={editing ? update.form({ user: user.id }) : store.form()}
            schema={schema}
            title={editing ? 'users.edit.title' : 'users.create.title'}
            headingParams={editing ? { name: user.name } : undefined}
            description={
                editing ? 'users.edit.description' : 'users.create.description'
            }
            submit={editing ? 'users.edit.submit' : 'users.create.submit'}
            submitting={
                editing ? 'users.edit.submitting' : 'users.create.submitting'
            }
        >
            {({ errors }) => (
                <div className="space-y-4">
                    <TextField
                        name="name"
                        label="users.field.name"
                        placeholder="users.field.name_placeholder"
                        defaultValue={user?.name ?? ''}
                        error={errors.name}
                    />

                    <TextField
                        name="email"
                        label="users.field.email"
                        type="email"
                        hint="users.field.email_hint"
                        placeholder="users.field.email_placeholder"
                        defaultValue={user?.email ?? ''}
                        error={errors.email}
                    />

                    <ComboboxField
                        name="role_id"
                        label="users.field.role"
                        options={roles}
                        defaultValue={user?.role_id ?? null}
                        placeholder="users.field.role_placeholder"
                        searchPlaceholder="users.field.role_search"
                        emptyMessage="users.field.role_empty"
                        error={errors.role_id}
                    />

                    <TextField
                        name="password"
                        label="users.field.password"
                        type="password"
                        autoComplete="new-password"
                        hint={
                            editing
                                ? 'users.field.password_optional_hint'
                                : 'users.field.password_hint'
                        }
                        placeholder="users.field.password_placeholder"
                        error={errors.password}
                        optional={editing}
                    />

                    <TextField
                        name="password_confirmation"
                        label="users.field.password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        error={errors.password_confirmation}
                        optional={editing}
                    />
                </div>
            )}
        </ResourceFormDialog>
    );
}
