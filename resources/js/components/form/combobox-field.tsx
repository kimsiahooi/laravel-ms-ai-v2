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

/** A row the workspace owns — see App\Data\OptionData. */
type Option = App.Data.OptionData;

type Props = {
    /** The name the server reads, and the key the error bag files a failure under. */
    name: string;
    label: TranslationKey;
    options: Option[];
    /** A line under the control explaining the choice. See {@see TextField}. */
    hint?: TranslationKey;
    error?: string;
    /** The chosen row's id. */
    defaultValue?: number | null;
    /** Shown on the trigger when nothing is chosen. */
    placeholder: TranslationKey;
    /** Shown in the search box inside the popover. */
    searchPlaceholder: TranslationKey;
    /** Shown when the search matches nothing. */
    emptyMessage: TranslationKey;
    /** Adds a "not set" entry and drops the required marker. */
    optional?: boolean;
};

/** Reserved for the "clear this" entry — no row can have it, since ids are numbers. */
const NONE = '__none__';

/**
 * A searchable picker over rows the workspace owns: a category, a supplier, a raw
 * material.
 *
 * **Why this is not {@see SelectField}.** The difference is not the search box, it is
 * where the words come from. A country and a unit are a fixed list whose labels are
 * translated strings looked up in `lang/`; a supplier's name is the workspace's own
 * data and is the same in every language. Squeezing both into one component would mean
 * a label that is sometimes a `TranslationKey` and sometimes a string, which is exactly
 * the kind of union that gets resolved wrongly six months later.
 *
 * The list is filtered in the browser rather than on the server. A workspace has tens
 * of categories and hundreds of suppliers, so the whole list is a page prop and the
 * filtering costs nothing — no round trip per keystroke, and it still works with a
 * flaky connection.
 *
 * Submits through a hidden input for the same reason SelectField does: the visible
 * control is Radix's and the wire value is ours, so the two need something to agree
 * through. `''` means not chosen, which is what `nullable` accepts.
 */
export function ComboboxField({
    name,
    label,
    options,
    hint,
    error,
    defaultValue,
    placeholder,
    searchPlaceholder,
    emptyMessage,
    optional,
}: Props) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(
        defaultValue === null || defaultValue === undefined
            ? ''
            : String(defaultValue),
    );

    const describedBy =
        [hint ? hintId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    // Item values are ids, so cmdk's own matching would search the digits. This maps an
    // id back to the name a person is actually typing, and the filter below uses it.
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

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {t(label)}
                {optional && (
                    <>
                        {' '}
                        <span className="font-normal text-muted-foreground">
                            {t('common.field.optional')}
                        </span>
                    </>
                )}
            </Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={!!error}
                        aria-describedby={describedBy}
                        className="w-full justify-between font-normal"
                    >
                        <span
                            className={cn(
                                'truncate',
                                !chosen && 'text-muted-foreground',
                            )}
                        >
                            {chosen ? chosen.name : t(placeholder)}
                        </span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    {/*
                        Substring, not cmdk's fuzzy scoring. Someone typing "steel" into
                        a list of suppliers expects the ones containing "steel" and
                        nothing else; a fuzzy match also returns anything whose letters
                        happen to appear in order, which reads as a bug.

                        "Not set" drops out as soon as anything is typed. Keeping it —
                        the obvious reading, since it is an action rather than a match —
                        means cmdk always has one visible item, so CommandEmpty never
                        renders and a search matching nothing shows a lone "Not set" with
                        no word about why. It is still one keystroke away: clear the box.
                    */}
                    <Command
                        filter={(itemValue, search) => {
                            if (itemValue === NONE) {
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
                                {optional && (
                                    <CommandItem
                                        value={NONE}
                                        onSelect={() => {
                                            setValue('');
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                value !== '' && 'opacity-0',
                                            )}
                                        />
                                        <span className="text-muted-foreground">
                                            {t('common.field.none')}
                                        </span>
                                    </CommandItem>
                                )}
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={String(option.id)}
                                        onSelect={(next) => {
                                            setValue(next);
                                            setOpen(false);
                                        }}
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
