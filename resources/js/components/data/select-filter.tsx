import { useId } from 'react';
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

/** Reserved for the "everything" entry — Radix's Select refuses an empty item value. */
const ALL = '__all__';

type Option = {
    /** What travels in the URL — a unit code, a status. */
    value: string;
    /** The words for it, looked up per render. */
    label: TranslationKey;
};

/**
 * A one-choice filter for a list: all units, or just the kilograms.
 *
 * Beside {@see ComboboxField} rather than instead of it, and the difference is the same
 * one that separates SelectField from ComboboxField in forms: the labels here are
 * translated strings from `lang/`, not the workspace's own data, and the list is short
 * enough that a search box would be furniture.
 *
 * It submits no input: a filter is not a form field, it has no name on the wire and
 * nothing reads it on submit. It does carry a real `<Label>`, because these stack
 * inside {@see FilterPanel} where the trigger's own text ("All units") says what is
 * selected but not what it selects.
 *
 * Clearing sends `''`, which {@see FilterApi} turns into a key absent from the URL —
 * so "all" is the absence of a filter rather than a value meaning everything.
 */
export function SelectFilter({
    value,
    onChange,
    options,
    label,
    allLabel,
}: {
    /** The value in force, or `''` for no filter. */
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    /** What this narrows by — "Unit", "Warehouse", "Status". */
    label: TranslationKey;
    /** The "no filter" entry, e.g. `raw-materials.filter.all_units`. */
    allLabel: TranslationKey;
}) {
    const { t } = useTranslation();
    const id = useId();

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{t(label)}</Label>

            <Select
                value={value === '' ? ALL : value}
                onValueChange={(next) => onChange(next === ALL ? '' : next)}
            >
                <SelectTrigger id={id} size="sm" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>{t(allLabel)}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {t(option.label)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
