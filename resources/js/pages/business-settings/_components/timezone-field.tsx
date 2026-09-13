import { Check, ChevronsUpDown } from 'lucide-react';
import { useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
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

/**
 * The workspace's clock, picked from the tzdb.
 *
 * **Why neither shared picker fits.** {@see ComboboxField} is keyed on numeric row ids —
 * a category, a supplier — and a zone's value is its own name. {@see SelectField} wants a
 * `TranslationKey` per option, and there are four hundred of them, none of which is
 * translated: `Asia/Kuala_Lumpur` is that string in every language because it is an
 * identifier, not a word. A fixed list, searchable, string-valued, untranslated labels is
 * a third case, so it gets its own component rather than a union bolted onto one of
 * theirs.
 *
 * Module-private on purpose. The day a second screen needs to pick a zone this can move to
 * `components/form/`; until then it is the settings screen's business.
 *
 * **Search is a substring match, not cmdk's fuzzy scoring** — the same call
 * `ComboboxField` makes and for the same reason. Someone typing `kuala` wants the zones
 * containing "kuala"; fuzzy matching also returns every identifier whose letters happen to
 * appear in that order, which reads as a broken filter rather than a clever one.
 *
 * Submits through a hidden input, as every picker in this app does: the visible control is
 * Radix's and the wire value is ours.
 */
export function TimezoneField({
    name,
    options,
    defaultValue,
    error,
}: {
    name: string;
    /** Every IANA identifier the server will accept — see TimeZones::options(). */
    options: string[];
    defaultValue: string;
    error?: string;
}) {
    const { t } = useTranslation();
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(defaultValue);

    const describedBy = [hintId, error ? errorId : null]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{t('business-settings.field.timezone')}</Label>

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
                        {/* An identifier, not a sentence — it is the same string in
                            every locale. i18n-allow */}
                        <span className="truncate">{value}</span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    <Command
                        filter={(itemValue, search) =>
                            itemValue
                                .toLowerCase()
                                .includes(search.toLowerCase())
                                ? 1
                                : 0
                        }
                    >
                        <CommandInput
                            placeholder={t(
                                'business-settings.field.timezone_search',
                            )}
                        />
                        <CommandList>
                            <CommandEmpty>
                                {t('business-settings.field.timezone_empty')}
                            </CommandEmpty>
                            {/* No CommandGroup: four hundred rows under one unlabelled
                                heading is a wrapper that renders nothing a reader can
                                use. */}
                            {options.map((zone) => (
                                <CommandItem
                                    key={zone}
                                    value={zone}
                                    // The mapped `zone`, not cmdk's callback argument.
                                    // cmdk normalises an item's value before handing it
                                    // back — it trims today and older versions
                                    // lowercased — and a lowercased `asia/kuala_lumpur`
                                    // is not a zone the tzdb knows, so the server would
                                    // refuse a value this list offered. Closing over the
                                    // string removes the question.
                                    onSelect={() => {
                                        setValue(zone);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            value !== zone && 'opacity-0',
                                        )}
                                    />
                                    {/* i18n-allow */}
                                    {zone}
                                </CommandItem>
                            ))}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            <input type="hidden" name={name} value={value} />

            <p id={hintId} className="text-muted-foreground text-xs">
                {t('business-settings.field.timezone_hint')}
            </p>

            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
