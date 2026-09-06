import { ChevronsUpDown } from 'lucide-react';
import { useId, useState } from 'react';
import { usePickedValues } from '@/components/data/use-picked-values';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

type Option = {
    /** What travels in the URL — a reason code, a status. */
    value: string;
    /** The words for it, looked up per render. */
    label: TranslationKey;
};

/**
 * A several-at-once filter over a short, translated list.
 *
 * The quadrant the other two leave empty. {@see SelectFilter} translates its choices but
 * takes one; {@see ComboboxFilter} takes several but searches the workspace's own rows.
 * A movement reason is neither: ten values at most, named in `lang/`, and only the ones
 * a workspace has actually used are ever offered — so a search box would be furniture
 * over four items, and a single choice makes "everything except transfers" impossible.
 *
 * **Several at once means ANY of them**, the same reading as ComboboxFilter: ticking a
 * second reason widens the result rather than narrowing it. The popover stays open while
 * ticking, because picking three things and reopening the list twice is not a control
 * anyone would choose.
 *
 * Checkboxes rather than cmdk rows, because there is nothing to search and a checkbox
 * says "several" before it is used, where a list of rows has to be tried first.
 */
export function CheckboxFilter({
    value,
    onChange,
    options,
    label,
    allLabel,
    manyLabel,
    hint,
}: {
    /** The values in force, comma-separated, or `''` for no filter. */
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    /** What this narrows by — "Reason", "Status". */
    label: TranslationKey;
    /** The "no filter" entry, e.g. `stock-movements.filter.all_reasons`. */
    allLabel: TranslationKey;
    /** Shown on the trigger for two or more, interpolating `:count`. */
    manyLabel: TranslationKey;
    /** What ticking several actually does, under the control. Pluralised on how many. */
    hint?: TranslationKey;
}) {
    const { t, tChoice } = useTranslation();
    const id = useId();
    const hintId = `${id}-hint`;
    const [open, setOpen] = useState(false);
    const { picked, toggle, clear } = usePickedValues(value, onChange);

    // One is worth naming; several are worth counting. "Transfer in" says more than
    // "1 reason", and "4 reasons" says more than four truncated words.
    const only =
        picked.length === 1
            ? options.find((option) => option.value === picked[0])
            : undefined;

    const triggerText =
        picked.length === 0
            ? t(allLabel)
            : only
              ? t(only.label)
              : t(manyLabel, { count: picked.length });

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{t(label)}</Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        size="sm"
                        role="combobox"
                        aria-expanded={open}
                        aria-describedby={hint ? hintId : undefined}
                        className="w-full justify-between font-normal"
                    >
                        <span className="truncate">{triggerText}</span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="max-h-[24rem] w-(--radix-popover-trigger-width) overflow-y-auto p-2"
                    align="start"
                >
                    {/*
                        Closes the popover, unlike the rows: "any reason" is a decision
                        that is finished, while ticking one is usually not.
                    */}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="w-full justify-start font-normal text-muted-foreground"
                        disabled={picked.length === 0}
                        onClick={() => {
                            clear();
                            setOpen(false);
                        }}
                    >
                        {t(allLabel)}
                    </Button>

                    <Separator className="my-1" />

                    <div className="space-y-0.5">
                        {options.map((option) => (
                            // Associated by id rather than by wrapping: Radix renders a
                            // button, which neither a browser nor a linter will accept as
                            // a label's implicit control. The whole row is still the
                            // target rather than a 16px square beside the words.
                            <div
                                key={option.value}
                                className="flex items-center gap-2 rounded-sm px-2 py-1.5 hover:bg-accent"
                            >
                                <Checkbox
                                    id={`${id}-${option.value}`}
                                    checked={picked.includes(option.value)}
                                    onCheckedChange={() => toggle(option.value)}
                                />
                                <Label
                                    htmlFor={`${id}-${option.value}`}
                                    className="flex-1 cursor-pointer font-normal text-sm"
                                >
                                    {t(option.label)}
                                </Label>
                            </div>
                        ))}
                    </div>
                </PopoverContent>
            </Popover>

            {/*
                Under the control rather than inside the popover: the popover is shut
                whenever somebody is looking at the results, which is exactly when they
                are working out what the filter did.
            */}
            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {tChoice(hint, picked.length, { count: picked.length })}
                </p>
            )}
        </div>
    );
}
