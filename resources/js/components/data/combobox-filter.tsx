import { Check, ChevronsUpDown } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import { usePickedValues } from '@/components/data/use-picked-values';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/** A row the workspace owns — see App\Data\OptionData. */
type Option = App.Data.OptionData;

/** Reserved for the "everything" entry. No row can have it, since ids are numbers. */
const ALL = '__all__';

/**
 * A searchable, multi-select filter over rows the workspace owns: raw materials, and
 * later warehouses and customers.
 *
 * The {@see SelectFilter} of workspace data, and the same split that separates
 * ComboboxField from SelectField in forms: a unit is a fixed list of translated words
 * and fits in a plain select; a material is the workspace's own data, there can be
 * hundreds, and scrolling to one without a search box is not a control anyone would
 * choose.
 *
 * **Several at once, meaning ANY of them.** Ticking a second material widens the
 * result rather than narrowing it — see the controller for why that is the useful
 * reading. The popover stays open while ticking, because picking three things and
 * reopening the list twice is not a control either.
 *
 * **The value is a comma-separated string, not an array**, and that is deliberate: it
 * is what travels in the URL, and a string keeps this component's effect dependencies
 * stable. An array prop would be a new object on every render, so the debounce below
 * would restart on every render and never fire.
 *
 * Deliberately parallel to {@see ComboboxField} rather than sharing with it. The
 * differences are not cosmetic — this is **controlled**, because a filter's value lives
 * in the URL and has to change when something else clears it; it submits no hidden
 * input, because a filter is not a form field; and its clear entry says "Any material"
 * rather than "Not set", because absence here means everything rather than nothing.
 */
export function ComboboxFilter({
    value,
    onChange,
    options,
    label,
    allLabel,
    manyLabel,
    searchPlaceholder,
    emptyMessage,
    hint,
}: {
    /** The ids in force, comma-separated, or `''` for no filter. */
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    /** What this narrows by — "Built from", "Warehouse". */
    label: TranslationKey;
    /** The "no filter" entry, e.g. `products.filter.all_materials`. */
    allLabel: TranslationKey;
    /** Shown on the trigger for two or more, interpolating `:count`. */
    manyLabel: TranslationKey;
    searchPlaceholder: TranslationKey;
    emptyMessage: TranslationKey;
    /**
     * What ticking several actually does, under the control.
     *
     * Pluralised on how many are ticked, not on the data: a multi-select is only
     * ambiguous once there are two in it, so the sentence is free to teach the
     * capability while the list is short and state the reading once it is not. Both
     * halves stay in the module's own lang file — this component does not know that
     * "any" is the rule, only that there is one worth saying.
     */
    hint?: TranslationKey;
}) {
    const { t, tChoice } = useTranslation();
    const id = useId();
    const hintId = `${id}-hint`;
    const [open, setOpen] = useState(false);

    const { picked, toggle, clear } = usePickedValues(value, onChange);

    // Item values are ids, so cmdk's own matching would search the digits. This maps an
    // id back to the name somebody is actually typing, and the filter below uses it.
    const names = useMemo(
        () =>
            new Map(
                options.map((option) => [
                    String(option.id),
                    option.name.toLowerCase(),
                ]),
            ),
        [options],
    );

    // One is worth naming; several are worth counting. "Serbuk kayu" says more than
    // "1 material", and "4 materials" says more than four truncated names.
    const only =
        picked.length === 1
            ? options.find((option) => String(option.id) === picked[0])
            : undefined;

    const triggerText =
        picked.length === 0
            ? t(allLabel)
            : (only?.name ?? t(manyLabel, { count: picked.length }));

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
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    {/*
                        Substring, not cmdk's fuzzy scoring — the same choice, for the
                        same reason, as ComboboxField: someone typing "steel" expects
                        the materials containing "steel" and nothing else.

                        "All materials" drops out as soon as anything is typed. Keeping
                        it would mean cmdk always has one visible item, so CommandEmpty
                        would never render and a search matching nothing would show a
                        lone "All materials" with no word about why.
                    */}
                    <Command
                        filter={(itemValue, search) => {
                            if (itemValue === ALL) {
                                return search === '' ? 1 : 0;
                            }

                            return (names.get(itemValue) ?? '').includes(
                                search.toLowerCase(),
                            )
                                ? 1
                                : 0;
                        }}
                    >
                        <CommandInput placeholder={t(searchPlaceholder)} />
                        <CommandList>
                            <CommandEmpty>{t(emptyMessage)}</CommandEmpty>
                            <CommandGroup>
                                {/*
                                    Closes the popover, unlike the rows: "any material"
                                    is a decision that is finished, while ticking one
                                    is usually not.
                                */}
                                <CommandItem
                                    value={ALL}
                                    onSelect={() => {
                                        clear();
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            picked.length > 0 && 'opacity-0',
                                        )}
                                    />
                                    <span className="text-muted-foreground">
                                        {t(allLabel)}
                                    </span>
                                </CommandItem>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={String(option.id)}
                                        onSelect={toggle}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                !picked.includes(
                                                    String(option.id),
                                                ) && 'opacity-0',
                                            )}
                                        />
                                        {option.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {/*
                Under the control rather than inside the popover: the popover is shut
                whenever someone is looking at the results, which is exactly when they
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
