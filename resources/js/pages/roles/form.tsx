import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';
import { TextField } from '@/components/form/text-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { runGate } from '@/lib/validation/gate';
import { roleSchema } from '@/lib/validation/schemas/role';
import { PermissionMatrix } from '@/pages/roles/_components/permission-matrix';
import { create, edit, index, store, update } from '@/routes/roles';

type Props = {
    /** The role being edited, or null while creating one. */
    role: App.Data.RoleData | null;
    /** The whole catalog, grouped by screen. {@see PermissionMatrix} renders it. */
    groups: App.Data.PermissionGroupData[];
};

/**
 * Writing a role: what it is called, and everything it reaches.
 *
 * **A page, where every short form in this app is a dialog.** One text box would not earn
 * one — sixty-three checkboxes do. A dialog would have to be scrolled to reach its own submit
 * button, and it would put the running count somewhere nobody can see while ticking. The same
 * argument the order forms make, and the same conclusion.
 *
 * That shape is only available because `TenantPermissions::routeMap()` auto-maps
 * `{screen}.create` and `{screen}.edit`. Until it did, a GET form page was a route name the
 * permission gate could not find — which it treats as open to any signed-in user, and which is
 * how two order catalogs came to be readable by anybody with a login.
 *
 * **Create and edit are one screen, told apart by `role` being null.** They validate
 * identically and post to the same Action, so the differences are a URL and four strings.
 *
 * **The name is uncontrolled and the ticks are not.** The DOM keeps what was typed, the way
 * every form here does; the set has to be state because the footer counts it and because a
 * group's third checkbox state depends on its siblings. `useForm` is the envelope rather than
 * the store — the error bag, the in-flight flag, and `transform`, where the payload is
 * assembled — so what gets sent is something this file states outright.
 *
 * **The payload is ordered by the catalog, not by click order.** A `Set` remembers insertion,
 * which would make two identical roles post different arrays depending on which box somebody
 * pressed first. Nothing downstream cares, and that is exactly why it should be deterministic.
 */
export default function RoleForm({ role, groups }: Props) {
    const { t, tChoice } = useTranslation();

    // Seeded once from the server's answer and owned here after that.
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(role?.permissions ?? []),
    );

    const catalog = useMemo(
        () =>
            groups.flatMap((group) =>
                group.permissions.map((permission) => permission.name),
            ),
        [groups],
    );

    const schema = useMemo(() => roleSchema(catalog), [catalog]);

    // Derived from the catalog rather than read off the Set, so the figure in the footer is
    // the figure that gets posted. They can differ: a tenant seeded before production orders
    // were dropped still carries three permission rows the catalog no longer names, and a role
    // holding one would otherwise count something the grid does not show.
    const chosen = catalog.filter((name) => selected.has(name));

    const form = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? [],
    });

    const errors = form.errors as Record<string, string>;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const typed = new FormData(event.currentTarget).get('name');

        const payload = {
            name: typeof typed === 'string' ? typed.trim() : '',
            // Catalog order — see the note above.
            permissions: chosen,
        };

        form.transform(() => payload);

        const options = {
            preserveScroll: true,
            onBefore: () => runGate(schema, payload, form, t),
        };

        if (role === null) {
            form.post(store().url, options);
        } else {
            form.patch(update({ role: role.id }).url, options);
        }
    };

    const title =
        role === null
            ? t('roles.create.title')
            : t('roles.edit.title', { name: role.name });

    setLayoutProps({
        breadcrumbs: [
            { title: t('roles.title'), href: index() },
            role === null
                ? { title: t('roles.create.crumb'), href: create() }
                : {
                      title: t('roles.edit.crumb'),
                      href: edit({ role: role.id }),
                  },
        ],
    });

    return (
        <>
            <Head title={title} />

            <div className="max-w-2xl space-y-1">
                <h1 className="font-semibold text-2xl tracking-tight">
                    {title}
                </h1>
                <p className="text-muted-foreground text-sm">
                    {role === null
                        ? t('roles.create.subtitle')
                        : // The stake, said while somebody is about to untick something.
                          tChoice('roles.edit.holders', role.holders, {
                              count: role.holders,
                          })}
                </p>
            </div>

            {/* `noValidate`, or the browser's own bubble fires on the `required` name box
                and the zod gate never runs. */}
            <form onSubmit={submit} noValidate className="space-y-6">
                <Card>
                    <CardContent>
                        <div className="max-w-md">
                            <TextField
                                name="name"
                                label="roles.field.name"
                                hint="roles.field.name_hint"
                                placeholder="roles.field.name_placeholder"
                                defaultValue={role?.name ?? ''}
                                error={errors.name}
                                autoFocus={role === null}
                            />
                        </div>
                    </CardContent>
                </Card>

                <PermissionMatrix
                    groups={groups}
                    selected={selected}
                    onChange={setSelected}
                    error={errors.permissions}
                />

                {/*
                    Sticky, and pulled out to the full width of the layout's own padding
                    (`p-4 md:p-6`) so it reads as a bar rather than a card that will not
                    scroll. The count is the only feedback that makes a grid this size
                    comprehensible — nineteen cards is more than one screenful, and
                    "8 of 63" is the answer to "have I finished".
                */}
                <div className="sticky bottom-0 -mx-4 -mb-4 flex flex-col gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between md:-mx-6 md:-mb-6 md:px-6">
                    <p className="text-muted-foreground text-sm tabular-nums">
                        {t('roles.matrix.selected', {
                            selected: chosen.length,
                            total: catalog.length,
                        })}
                    </p>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href={index()}>
                                {t('common.actions.cancel')}
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {t(
                                role === null
                                    ? form.processing
                                        ? 'roles.create.submitting'
                                        : 'roles.create.submit'
                                    : form.processing
                                      ? 'roles.edit.submitting'
                                      : 'roles.edit.submit',
                            )}
                        </Button>
                    </div>
                </div>
            </form>
        </>
    );
}
