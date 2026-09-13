import { useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { TriStateCheckbox } from '@/pages/roles/_components/tri-state-checkbox';

type Group = App.Data.PermissionGroupData;

/**
 * One screen's row of the matrix: what may be done to it, and a box that ticks the lot.
 *
 * **A real `<fieldset>` with a real `<legend>`**, which is what makes the checkboxes readable
 * out loud. A screen reader announces the group before each box inside it, so "Edit" alone is
 * heard as "Categories — Edit" without anybody composing that sentence on the server. See
 * {@see \App\Data\PermissionOptionData} for the version that did, and the three ways it was
 * wrong.
 *
 * **No words cross the wire.** The server sends `screen` and `action` as enum cases and the
 * keys are composed here — the house pattern, in its thirteenth place. `#[TypeScript]` on both
 * enums is what makes the template literal type-check, so a screen added to the catalog
 * without its translation is a `tsc` error rather than a blank legend.
 *
 * **The legend is hidden and the select-all carries the visible name**, so the screen is
 * written once rather than twice. Clicking it ticks everything in the group, which is what
 * somebody reaching for a screen's name is usually after; the box's own accessible name says
 * so explicitly, because "Categories" as the label of a checkbox is not an instruction.
 */
export function PermissionGroupCard({
    group,
    selected,
    onToggle,
}: {
    group: Group;
    /** Every name ticked across the whole matrix, not only this group's. */
    selected: ReadonlySet<string>;
    /** Tick or clear a set of names at once — one box, or the whole group. */
    onToggle: (names: string[], checked: boolean) => void;
}) {
    const { t } = useTranslation();
    const uid = useId();

    const screen = t(`permissions.screen.${group.screen}` as const);
    const names = group.permissions.map((permission) => permission.name);
    const chosen = names.filter((name) => selected.has(name)).length;

    return (
        <fieldset className="rounded-lg border bg-card p-4">
            {/* The group's accessible name. Hidden rather than absent — the visible copy is
                the select-all's label, and two identical headings is one too many. */}
            <legend className="sr-only">{screen}</legend>

            <div className="flex items-center gap-2">
                <TriStateCheckbox
                    id={`${uid}-all`}
                    // Radix's third state, which is the honest answer for a partly ticked
                    // group: neither on nor off, and pressing it turns the rest on. It draws
                    // a dash rather than a tick — see {@see TriStateCheckbox} for why the
                    // vendored primitive could not.
                    checked={
                        chosen === names.length
                            ? true
                            : chosen > 0
                              ? 'indeterminate'
                              : false
                    }
                    // Wins over the visible label for assistive technology, which is the
                    // point: sighted readers get the screen's name, everyone else gets what
                    // pressing it does.
                    aria-label={t('roles.matrix.group_all', { screen })}
                    onCheckedChange={(checked) =>
                        onToggle(names, checked === true)
                    }
                />
                <Label
                    htmlFor={`${uid}-all`}
                    className="flex-1 cursor-pointer font-medium text-sm"
                >
                    {screen}
                </Label>
                <span className="text-muted-foreground text-xs tabular-nums">
                    {t('roles.matrix.group_count', {
                        selected: chosen,
                        total: names.length,
                    })}
                </span>
            </div>

            <div className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2.5 border-t pt-3">
                {group.permissions.map((permission) => (
                    <div
                        key={permission.name}
                        className="flex items-center gap-2"
                    >
                        <Checkbox
                            id={`${uid}-${permission.action}`}
                            checked={selected.has(permission.name)}
                            onCheckedChange={(checked) =>
                                onToggle([permission.name], checked === true)
                            }
                        />
                        <Label
                            htmlFor={`${uid}-${permission.action}`}
                            className="cursor-pointer font-normal text-sm"
                        >
                            {/* The verb alone: the legend above already names the noun. */}
                            {t(
                                `permissions.action.${permission.action}` as const,
                            )}
                        </Label>
                    </div>
                ))}
            </div>
        </fieldset>
    );
}
