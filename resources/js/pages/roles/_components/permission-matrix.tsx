import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { PermissionGroupCard } from '@/pages/roles/_components/permission-group-card';

type Group = App.Data.PermissionGroupData;

/**
 * The whole catalog as a grid of screens, and the two buttons that move all of it at once.
 *
 * **Controlled, not stateful.** The chosen set belongs to the form — it is what gets
 * submitted, and a component that owned it would have to hand it back on every keystroke
 * anyway. What lives here is the arithmetic: one toggle callback that takes a list of names,
 * so ticking one box and ticking a whole screen are the same operation with a different-length
 * array.
 *
 * **Nineteen cards rather than nineteen collapsibles.** Collapsing them by default on a phone
 * and leaving them open on a desktop would mean an initial state that depends on the viewport,
 * which is a different first render on the server than in the browser — the hydration
 * mismatch (React error 418) that nothing in this project would catch. So every card renders open at every width, each one
 * short enough to read at a glance, and the sticky footer carries the count so the page stays
 * navigable however long it is.
 *
 * **The heading is the focus target.** `runGate` puts the cursor on the first invalid field by
 * name or id, and there is no input called `permissions` — the grid is sixty-three boxes with
 * their own names. `id="permissions"` plus `tabIndex={-1}` gives the gate something to land on,
 * so an empty matrix scrolls its own message into view instead of failing silently at the
 * bottom of a long page.
 */
export function PermissionMatrix({
    groups,
    selected,
    onChange,
    error,
}: {
    /** The catalog, in the order `TenantPermissions::matrix()` emits it. */
    groups: Group[];
    selected: ReadonlySet<string>;
    onChange: (next: Set<string>) => void;
    /** Whichever of the zod gate's or Laravel's messages arrived for the field. */
    error?: string;
}) {
    const { t } = useTranslation();

    const everything = useMemo(
        () =>
            groups.flatMap((group) =>
                group.permissions.map((permission) => permission.name),
            ),
        [groups],
    );

    const toggle = (names: string[], checked: boolean) => {
        const next = new Set(selected);

        for (const name of names) {
            if (checked) {
                next.add(name);
            } else {
                next.delete(name);
            }
        }

        onChange(next);
    };

    return (
        <Card>
            <CardContent className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h2
                            id="permissions"
                            tabIndex={-1}
                            className="font-medium"
                        >
                            {t('roles.matrix.heading')}
                        </h2>
                        <p className="max-w-2xl text-muted-foreground text-sm">
                            {t('roles.matrix.description')}
                        </p>
                    </div>

                    {/* `type="button"`, or either of these submits the form. */}
                    <div className="flex shrink-0 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => toggle(everything, true)}
                        >
                            {t('roles.matrix.select_all')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => toggle(everything, false)}
                        >
                            {t('roles.matrix.clear_all')}
                        </Button>
                    </div>
                </div>

                <InputError message={error} />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {groups.map((group) => (
                        <PermissionGroupCard
                            key={group.screen}
                            group={group}
                            selected={selected}
                            onToggle={toggle}
                        />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
