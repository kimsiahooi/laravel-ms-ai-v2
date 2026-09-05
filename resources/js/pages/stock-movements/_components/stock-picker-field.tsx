import { Check, ChevronsUpDown } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
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

export type StockPickerEntry = {
    /** What the hidden input submits. A warehouse id, or `product:5` for an item. */
    value: string;
    /** The name somebody is looking for. */
    primary: string;
    /**
     * The thing that tells two identical names apart — a warehouse's site, an item's
     * SKU. Searched as well as shown, because "PEN-A" is a reasonable thing to type.
     */
    secondary: string;
    /** A heading to file this entry under. Entries sharing one render together. */
    group?: TranslationKey;
};

/**
 * A searchable picker for the stock screens: two lines per row, optional headings.
 *
 * **Why not {@see ComboboxField}.** Two reasons, and neither is cosmetic. Its value is a
 * number, and an item here addresses two tables at once so its value is `product:5` —
 * see `App\Support\StockItem`. And its rows are one line, which is fine for a supplier
 * and wrong for a warehouse: two sites with a "Main store" are ordinary, and a picker
 * offering "Main store" twice is a picker you cannot use.
 *
 * Lives in this module's `_components/` rather than `components/form/` because it has
 * exactly one consumer today. Transfers and stock takes are its second and third, and
 * that is when it should move — see the rule of three in ARCHITECTURE.md.
 *
 * Submits through a hidden input, like every other field here: the visible control is
 * Radix's and the wire value is ours, so the two need something to agree through.
 */
export function StockPickerField({
    name,
    label,
    entries,
    defaultValue,
    onChange,
    error,
    placeholder,
    searchPlaceholder,
    emptyMessage,
    hint,
}: {
    name: string;
    label: TranslationKey;
    entries: StockPickerEntry[];
    defaultValue?: string | null;
    /**
     * Told what was picked, when something else on the form depends on it.
     *
     * The field keeps owning the value — the hidden input is still what the server
     * reads, and a caller that ignores this gets a control that works on its own. This
     * only exists so a *second* thing can react, which for movements is the on-hand
     * line: it needs the warehouse and the item together, and neither picker knows
     * about the other.
     */
    onChange?: (value: string) => void;
    error?: string;
    placeholder: TranslationKey;
    searchPlaceholder: TranslationKey;
    emptyMessage: TranslationKey;
    hint?: TranslationKey;
}) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const [open, setOpen] = useState(false);
    const [chosen, setChosen] = useState(defaultValue ?? '');

    const selected = entries.find((entry) => entry.value === chosen);

    // Grouped in the order the groups first appear, which is the order the server sent
    // them — products before materials, sites alphabetically. Not re-sorted here: the
    // list arrived in a deliberate order and a second opinion about it would only make
    // the two disagree.
    const groups = useMemo(() => {
        const byGroup = new Map<TranslationKey | '', StockPickerEntry[]>();

        for (const entry of entries) {
            const key = entry.group ?? '';
            const bucket = byGroup.get(key);

            if (bucket === undefined) {
                byGroup.set(key, [entry]);
            } else {
                bucket.push(entry);
            }
        }

        return [...byGroup.entries()];
    }, [entries]);

    // Both lines are searchable, and cmdk is told to match on this rather than on the
    // value — the value is an id, and nobody types an id.
    const haystack = useMemo(
        () =>
            new Map(
                entries.map((entry) => [
                    entry.value,
                    `${entry.primary} ${entry.secondary}`.toLowerCase(),
                ]),
            ),
        [entries],
    );

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{t(label)}</Label>

            <input type="hidden" name={name} value={chosen} />

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={error !== undefined}
                        aria-describedby={
                            [hint ? hintId : null, error ? errorId : null]
                                .filter(Boolean)
                                .join(' ') || undefined
                        }
                        className="w-full justify-between font-normal"
                    >
                        {selected === undefined ? (
                            <span className="text-muted-foreground">
                                {t(placeholder)}
                            </span>
                        ) : (
                            <span className="truncate">
                                {selected.primary}
                                <span className="ml-2 text-muted-foreground">
                                    {selected.secondary}
                                </span>
                            </span>
                        )}
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    {/* Substring, not cmdk's fuzzy scoring — the same choice, for the
                        same reason, as ComboboxField. */}
                    <Command
                        filter={(value, search) =>
                            (haystack.get(value) ?? '').includes(
                                search.toLowerCase(),
                            )
                                ? 1
                                : 0
                        }
                    >
                        <CommandInput placeholder={t(searchPlaceholder)} />
                        <CommandList>
                            <CommandEmpty>{t(emptyMessage)}</CommandEmpty>
                            {groups.map(([group, rows]) => (
                                <CommandGroup
                                    key={group}
                                    heading={
                                        group === '' ? undefined : t(group)
                                    }
                                >
                                    {rows.map((entry) => (
                                        <CommandItem
                                            key={entry.value}
                                            value={entry.value}
                                            onSelect={(value) => {
                                                setChosen(value);
                                                onChange?.(value);
                                                setOpen(false);
                                            }}
                                        >
                                            <Check
                                                className={cn(
                                                    'size-4',
                                                    entry.value !== chosen &&
                                                        'opacity-0',
                                                )}
                                            />
                                            <span className="min-w-0 flex-1 truncate">
                                                {entry.primary}
                                            </span>
                                            <span className="shrink-0 text-muted-foreground text-xs">
                                                {entry.secondary}
                                            </span>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            ))}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {hint && (
                <p id={hintId} className="text-muted-foreground text-xs">
                    {t(hint)}
                </p>
            )}

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
