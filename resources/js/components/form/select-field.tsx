import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

export type SelectOption = {
    value: string;
    /** A key, not a sentence — the option list is data and has no locale of its own. */
    label: TranslationKey;
};

type Props = {
    /** The name the server reads, and the key the error bag files a failure under. */
    name: string;
    label: TranslationKey;
    options: SelectOption[];
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
    error,
    defaultValue,
    placeholder,
    optional,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const [value, setValue] = useState(defaultValue ?? '');

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
                    aria-describedby={error ? errorId : undefined}
                >
                    <SelectValue placeholder={t(placeholder)} />
                </SelectTrigger>
                <SelectContent>
                    {optional && (
                        <SelectItem value={NONE}>
                            {t('common.field.none')}
                        </SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {t(option.label)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <input type="hidden" name={name} value={value} />

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
