import { usePage } from '@inertiajs/react';
import { CalendarIcon } from 'lucide-react';
import { useId, useState } from 'react';
import { TimeSelect } from '@/components/form/time-select';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateValue, formatMonthCaption, weekdayName } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

type Props = {
    /** The field name the request expects. Carried by the hidden input. */
    name: string;
    label: TranslationKey;
    hint?: TranslationKey;
    /**
     * `''`, `Y-m-d`, or `Y-m-d HH:MM`. The starting value only — this is uncontrolled.
     * The three shapes are the wire contract; see the note on the hidden input below.
     */
    defaultValue?: string;
    error?: string;
    /** Appends "(optional)" to the label, matching {@see TextField}. */
    optional?: boolean;
    placeholder?: TranslationKey;
    /**
     * Offer a time of day beside the date. Off by default, so a field that wants a
     * plain calendar day keeps behaving exactly as it does today.
     */
    withTime?: boolean;
};

/**
 * A calendar date, picked from a shadcn calendar rather than typed into the browser's.
 *
 * **Why this exists at all.** The field it replaces was `<input type="date">`, which is
 * accessible and free but hands its popup to the browser: the dropdown is Chrome's, it
 * cannot be styled, and it renders `mm/dd/yyyy` or `dd/mm/yyyy` depending on the
 * viewer's OS while every other date on the page reads `15 Oct 2026`. This is one
 * control that looks like the rest of the app and reads the same way everywhere.
 *
 * **It stays uncontrolled from the form's point of view.** Every form in this app reads
 * itself with `new FormData(event.currentTarget)` on submit, so a picker that held its
 * value only in React state would submit nothing. The value lives in a hidden input
 * carrying `name`, which is what the form — and the server, and the zod gate — sees.
 * React state exists to redraw the button and the calendar, not to be the value.
 *
 * **Three things could make this render differently on the server and in the browser,
 * and all three are closed:**
 *
 * 1. *Today.* A calendar marks the current day, and `new Date()` in render is the one
 *    thing `scripts/ui-guard.sh` refuses — the two sides can land either side of
 *    midnight. `today` is a server prop, resolved in the workspace's own zone.
 * 2. *Month and weekday names.* react-day-picker formats them through date-fns, whose
 *    locale data is static and therefore safe — but it is a *second* source of those
 *    words, in English, beside the ones `lib/format.ts` already owns. Both are
 *    overridden through `formatters` so every date in this app is spelled by one table.
 * 3. *Day numbers.* Overridden too, so a locale with its own numerals cannot render
 *    digits the server did not.
 *
 * **The time sits beside the calendar, not inside the popover**, and that is a decision
 * rather than a layout accident. Inside, picking a day would have to stop closing the
 * popover — otherwise the calendar shuts before the time can be set — which would give one
 * control two behaviours depending on a prop, and need a Done button that commits nothing
 * (the value is already in the hidden input the moment a day is clicked). It would also
 * nest a Radix Select inside a Radix Popover, which this codebase nests inside a Dialog
 * but has never nested inside a Popover. Beside it, the calendar keeps the behaviour
 * people have already learned, and — the part that matters most — **a time is visible
 * without opening anything**.
 *
 * **No `Date` here ever meets a time zone, and nothing downstream does either.** A
 * calendar day is not an instant. `Date` objects are built from local Y/M/D fields and read
 * back the same way, so the value round-trips exactly; going through `toISOString()` would
 * hand back the previous day for every reader east of UTC. The server then stores what
 * arrives verbatim — see `PurchaseOrderRequest::expectedInstant()` — so the day that was
 * clicked is the day that is kept, and no timezone setting can move it later.
 */
export function DateField({
    name,
    label,
    hint,
    defaultValue = '',
    error,
    optional,
    placeholder,
    withTime,
}: Props) {
    const { t } = useTranslation();
    const today = usePage().props.today;
    const id = useId();
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;

    // Split, because the two halves are chosen by different controls and only mean
    // something joined. `value` below is derived rather than stored, so the hidden
    // input can never disagree with what the calendar and the selects are showing.
    const [date, setDate] = useState(() => defaultValue.split(' ')[0] ?? '');
    const [time, setTime] = useState(() => defaultValue.split(' ')[1] ?? '');
    const [open, setOpen] = useState(false);

    const selected = toDate(date);
    const value = date === '' ? '' : time === '' ? date : `${date} ${time}`;

    return (
        <div className="space-y-2">
            <Label htmlFor={id}>
                {t(label)}
                {optional === true && (
                    // The explicit `{' '}` is load-bearing, exactly as it is in
                    // TextField: an accessible name is built by concatenating text
                    // nodes and nothing is inserted between two inline elements, so a
                    // margin alone would announce "Expected dateoptional".
                    <>
                        {' '}
                        <span className="font-normal text-muted-foreground">
                            {t('common.field.optional')}
                        </span>
                    </>
                )}
            </Label>

            {/* The value the form actually submits, and the whole contract with the
                server: `''`, `Y-m-d`, or `Y-m-d HH:MM`. Everything above it is chrome.
                `PurchaseOrderRequest::expectedInstant()` tells the last two apart by
                the space, and `optionalDateTime` refuses any fourth shape. */}
            <input type="hidden" name={name} value={value} />

            {/* `flex-wrap`, so the time drops to its own line on a narrow column
                rather than squashing the trigger until the date truncates. */}
            <div className="flex flex-wrap items-center gap-2">
                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            id={id}
                            type="button"
                            variant="outline"
                            aria-invalid={error !== undefined}
                            aria-describedby={
                                [hint ? hintId : null, error ? errorId : null]
                                    .filter(Boolean)
                                    .join(' ') || undefined
                            }
                            className={cn(
                                // `min-w`, not `min-w-0`: the trigger must stay wide
                                // enough to read `15 Oct 2026` whole. Without a floor
                                // it shrinks to share the row with the two selects and
                                // the date spills out of it at 375. With one, the time
                                // group wraps to its own line instead, which is what
                                // `flex-wrap` on the row is there for.
                                'min-w-[10rem] flex-1 justify-start px-3 font-normal',
                                value === '' && 'text-muted-foreground',
                            )}
                        >
                            <CalendarIcon className="size-4 opacity-50" />
                            {date === ''
                                ? t(placeholder ?? 'common.field.pick_a_date')
                                : // The date half only. `formatDateValue` understands
                                  // `Y-m-d` and returns anything else unchanged, so the
                                  // composed value would print as raw `2026-10-15 14:30`.
                                  formatDateValue(date)}
                        </Button>
                    </PopoverTrigger>

                    <PopoverContent className="w-auto p-0" align="start">
                        <Calendar
                            mode="single"
                            selected={selected ?? undefined}
                            // Opens on the picked month, or on the current one when nothing
                            // is picked yet — never on whatever month a fresh Date lands in.
                            defaultMonth={
                                selected ?? toDate(today) ?? undefined
                            }
                            today={toDate(today) ?? undefined}
                            autoFocus
                            onSelect={(picked) => {
                                setDate(
                                    picked === undefined ? '' : toValue(picked),
                                );

                                // A time with no day is not a value, so deselecting the
                                // day takes the time with it.
                                if (picked === undefined) {
                                    setTime('');
                                }

                                setOpen(false);
                            }}
                            formatters={{
                                formatCaption: formatMonthCaption,
                                formatWeekdayName: weekdayName,
                                formatDay: (date) => String(date.getDate()),
                            }}
                        />

                        {/* An optional date needs a way back to "none". Clearing by
                        re-clicking the selected day is react-day-picker's own behaviour
                        and nothing on screen says so. */}
                        {optional === true && value !== '' && (
                            <div className="border-t p-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="w-full"
                                    onClick={() => {
                                        setDate('');
                                        setTime('');
                                        setOpen(false);
                                    }}
                                >
                                    {t('common.field.clear_date')}
                                </Button>
                            </div>
                        )}
                    </PopoverContent>
                </Popover>

                {withTime === true && (
                    <TimeSelect
                        value={time}
                        onChange={setTime}
                        disabled={date === ''}
                    />
                )}
            </div>

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
 * `2026-10-15` → a `Date` at local midnight on that day, or null.
 *
 * Built from the three fields rather than parsed from the string: `new Date('2026-10-15')`
 * reads the bare form as **UTC** midnight, which is the previous day's evening for every
 * reader east of it — so a calendar seeded that way would highlight 14 October in Kuala
 * Lumpur. The three-argument constructor is local by definition and has no such trap.
 */
function toDate(value: string): Date | null {
    const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (parts === null) {
        return null;
    }

    return new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]));
}

/**
 * A `Date` back to `2026-10-15`, read off the same local fields {@see toDate} wrote.
 *
 * Not `toISOString().slice(0, 10)`, which converts to UTC first and gives back the day
 * before for half the world — the round trip has to be symmetric or picking a date and
 * saving it would quietly store a different one.
 */
function toValue(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}
