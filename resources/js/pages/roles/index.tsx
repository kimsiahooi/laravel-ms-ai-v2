import { Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { NewRoleButton } from '@/pages/roles/_components/new-role-button';
import { RoleCard } from '@/pages/roles/_components/role-card';
import { index } from '@/routes/roles';

type Props = {
    /** Every role in the workspace, unpaginated and in name order. */
    roles: App.Data.RoleData[];
};

/**
 * The roles a workspace has.
 *
 * **Not a `DataTable`, deliberately.** A workspace has a handful of roles; a search box, a
 * pagination bar and a column picker over five rows is furniture around nothing. That is also
 * why there is no `TableKey` case for roles — see the controller, which says the same thing so
 * that nobody "fixes" the inconsistency from either end.
 *
 * **No empty state either, and it is not an omission.** Every workspace is seeded with an
 * Administrator role that cannot be deleted, so this list has at least one card in it from the
 * moment the workspace exists. The screen a new workspace needs is the one that explains how to
 * add a second role, which is what the first card's own copy does.
 *
 * The cards are the same three-column grid the editor's own screens use, so the list and the
 * thing it lists look like they belong to each other.
 */
export default function RolesIndex({ roles }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('roles.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('roles.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('roles.title')}
                    </h1>
                    <p className="max-w-2xl text-muted-foreground text-sm">
                        {t('roles.subtitle')}
                    </p>
                </div>
                <NewRoleButton />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {roles.map((role) => (
                    <RoleCard key={role.id} role={role} />
                ))}
            </div>
        </>
    );
}
