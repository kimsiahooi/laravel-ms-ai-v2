import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

export type SelectOption = {
    value: string;
    /** A key, not a sentence — the option list is data and has no locale of its own. */
    label: TranslationKey;
    /**
     * A heading to file this option under. Options carrying the same one are rendered
     * together, in the order they arrive.
     *
     * Per-option rather than a separate `groups` prop, so a caller with nothing to group
     * writes exactly what it wrote before. Fourteen units in a flat list is a wall; the
     * same fourteen under Mass, Volume, Length and Count is a menu.
     */
    group?: TranslationKey;
};

type Props = {
    /** The name the server reads, and the key the error bag files a failure under. */
    name: string;
    label: TranslationKey;
    options: SelectOption[];
    /** A line under the control explaining the choice. See {@see TextField}. */
    hint?: TranslationKey;
    error?: string;
    defaultValue?: string | null;
    /** Shown when nothing is chosen. */
    placeholder: TranslationKey;
    /** Adds a "not set" entry and drops the required marker. */
    optional?: boolean;
};

/**
 * Radix's Select refuses an item with an empty value — it reserves `''` for "nothing
 * selected" — so clearing a chosen option needs an entry with a value of its own.
 */
const NONE = '__none__';

/**
 * A labelled picker with its error underneath, matching {@see TextField}.
 *
 * **It submits through a hidden input rather than through the Select.** Radix does
 * bubble a native select when it detects a form, but what reaches the server then
 * depends on that detection and on the sentinel above leaking out of it. A hidden input
 * makes the wire value something this component states outright: the chosen code, or
 * `''` for not-set, which is exactly what `nullable` accepts.
 *
 * That is also why this one holds state while `TextField` does not. The visible control
 * is Radix's and the submitted value is ours, so the two need something to agree
 * through. Seeded from `defaultValue` and thrown away with the dialog, so it still
 * needs no reset.
 */
export function SelectField({
    name,
    label,
    options,
    hint,
    error,
    defaultValue,
    placeholder,
    optional,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const [value, setValue] = useState(defaultValue ?? '');

    // Both, when both exist — see TextField for why the hint is not dropped on error.
    const describedBy =
        [hint ? hintId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    const groups = groupOptions(options);

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {t(label)}
                {optional && (
                    // An explicit `{' '}`, not a margin. A margin is the obvious fix
                    // and only half of one: it puts a gap on screen, but the accessible
                    // name is built by concatenating text nodes, and nothing is inserted
                    // between two inline elements — so a screen reader announced
                    // "Barcodeoptional". `{' '}` survives (JSX drops whitespace between
                    // lines, not an expression) and fixes both at once.
                    <>
                        {' '}
                        <span className="font-normal text-muted-foreground">
                            {t('common.field.optional')}
                        </span>
                    </>
                )}
            </Label>

            {/*
                `value` is passed through as-is, empty string included: Radix reserves ''
                on the ROOT for "nothing selected" and shows the placeholder, and only
                forbids it on an ITEM. Mapping '' to the sentinel here instead would make
                an untouched field read "Not set" and the placeholder would never appear.
            */}
            <Select
                value={value}
                onValueChange={(next) => setValue(next === NONE ? '' : next)}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-invalid={!!error}
                    aria-describedby={describedBy}
                >
                    <SelectValue placeholder={t(placeholder)} />
                </SelectTrigger>
                <SelectContent>
                    {optional && (
                        <SelectItem value={NONE}>
                            {t('common.field.none')}
                        </SelectItem>
                    )}
                    {groups.map((group) => {
                        const items = group.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {t(option.label)}
                            </SelectItem>
                        ));

                        // An ungrouped list renders bare, exactly as it did before
                        // grouping existed — no empty heading, no extra wrapper.
                        return group.label === undefined ? (
                            items
                        ) : (
                            <SelectGroup key={group.label}>
                                <SelectLabel>{t(group.label)}</SelectLabel>
                                {items}
                            </SelectGroup>
                        );
                    })}
                </SelectContent>
            </Select>

            <input type="hidden" name={name} value={value} />

            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {t(hint)}
                </p>
            )}

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}

/**
 * Options in the order given, collected into runs that share a `group`.
 *
 * A run, not a bucket: options are never reordered, so what the server sent is what the
 * list shows. A caller that sets no groups gets exactly one ungrouped run back.
 */
function groupOptions(
    options: SelectOption[],
): { label?: TranslationKey; options: SelectOption[] }[] {
    const groups: { label?: TranslationKey; options: SelectOption[] }[] = [];

    for (const option of options) {
        const last = groups.at(-1);

        if (last && last.label === option.group) {
            last.options.push(option);
        } else {
            groups.push({ label: option.group, options: [option] });
        }
    }

    return groups;
}
