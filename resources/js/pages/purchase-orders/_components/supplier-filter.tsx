import { Check, ChevronsUpDown } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
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

/** Reserved for the "everything" entry. No row can have it, since ids are numbers. */
const ALL = '__all__';

/**
 * A searchable filter that narrows to exactly **one** supplier.
 *
 * **Why not {@see ComboboxFilter}, which looks like this and is in `components/`.** That
 * one is a multi-select: ticking a second material widens the result, and its value is a
 * comma-separated list. The orders list filters by a single supplier — the controller
 * reads one id and echoes one back — so a multi-select here would let somebody tick two,
 * send `3,7`, and watch the second tick quietly disappear on the round trip. A control
 * that offers a choice the server cannot honour is worse than one that offers less.
 *
 * **Why not {@see SelectFilter}.** The same split that separates ComboboxField from
 * SelectField in forms: a status is a fixed list of translated words and fits in a plain
 * select, while a supplier is the workspace's own data, there can be hundreds, and
 * scrolling to one without a search box is not a control anyone would choose.
 *
 * It lives here rather than in `components/data/` because it has one consumer. Sales
 * orders will want the same thing for a customer, and purchase returns for a supplier
 * again — that is the third, and the rule of three says it moves then rather than now.
 *
 * Controlled, and it submits nothing: a filter's value lives in the URL, so the value
 * arrives as a prop and every change goes back through {@see FilterApi}. Clearing sends
 * `''`, which drops the key from the URL — "any supplier" is the absence of a filter
 * rather than a value meaning everything.
 */
export function SupplierFilter({
    value,
    onChange,
    options,
    label,
    allLabel,
    searchPlaceholder,
    emptyMessage,
}: {
    /** The id in force, or `''` for no filter. */
    value: string;
    onChange: (value: string) => void;
    options: App.Data.OptionData[];
    label: TranslationKey;
    /** The "no filter" entry. */
    allLabel: TranslationKey;
    searchPlaceholder: TranslationKey;
    emptyMessage: TranslationKey;
}) {
    const { t } = useTranslation();
    const id = useId();
    const [open, setOpen] = useState(false);

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

    const chosen = options.find((option) => String(option.id) === value);

    const pick = (next: string) => {
        onChange(next === ALL ? '' : next);
        setOpen(false);
    };

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
                        className="w-full justify-between font-normal"
                    >
                        <span className="truncate">
                            {chosen?.name ?? t(allLabel)}
                        </span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    {/* Substring, not cmdk's fuzzy scoring, and "Any supplier" drops out
                        as soon as anything is typed — the same two choices ComboboxFilter
                        makes, for the same two reasons it gives. */}
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
                                <CommandItem value={ALL} onSelect={pick}>
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value !== '' && 'opacity-0',
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
                                        onSelect={pick}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                value !== String(option.id) &&
                                                    'opacity-0',
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
        </div>
    );
}
