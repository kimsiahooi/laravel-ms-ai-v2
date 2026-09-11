import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

/** `00`…`23`. Generated, never written out — see the note on literals below. */
const HOURS = Array.from({ length: 24 }, (_, hour) =>
    String(hour).padStart(2, '0'),
);

/** Quarter hours. The granularity a delivery slot is actually agreed at. */
const MINUTES = ['00', '15', '30', '45'];

/**
 * Radix reserves `''` for "nothing selected" and refuses an item carrying it, so
 * offering "no time" as a choice needs a value of its own. Same sentinel trick, and the
 * same reason, as {@see SelectField}'s `__none__`.
 */
const NO_TIME = '__no_time__';

/**
 * An optional time of day, as two selects — `14` : `30`.
 *
 * **Why not `<input type="time">`.** The same reason the date beside it is no longer
 * `<input type="date">`: the browser draws that control and its dropdown, it cannot be
 * styled to match anything, and whether it reads `14:30` or `02:30 PM` is decided by the
 * viewer's OS rather than by this app.
 *
 * **Why not {@see SelectField}.** Two reasons, both fatal rather than stylistic. Its
 * option `label` is typed `TranslationKey` and resolved through `t()` unconditionally, so
 * `"14"` is not a value it can render. And it submits through its own hidden input
 * carrying a `name`, which would put two stray fields on the wire that no request
 * validates. Composing the primitive is what `CLAUDE.md` prescribes for exactly this.
 *
 * **The digits are generated, not written.** `bun run check:i18n` flags a bare string in
 * JSX as an untranslated sentence; a value produced by an expression is not a literal, so
 * a mapped array passes where `<SelectItem>00</SelectItem>` would be reported. That is a
 * happy accident rather than the reason — the real reason is that twenty-four hand-typed
 * numbers is twenty-four chances to typo one.
 *
 * The value is a plain `HH:MM` string, or `''` for no time. This component owns no state:
 * the field above it does, because the two halves have to be joined before they mean
 * anything.
 */
export function TimeSelect({
    value,
    onChange,
    disabled,
}: {
    /** `HH:MM`, or `''` when no time is set. */
    value: string;
    onChange: (next: string) => void;
    /** True until a date exists — a time on no particular day is not a value. */
    disabled?: boolean;
}) {
    const { t } = useTranslation();
    const [hour = '', minute = ''] = value.split(':');

    /**
     * An hour that is not on the quarter-hour grid still has to be displayable.
     *
     * Every IANA offset is a whole number of quarter hours, so a time picked here stays
     * on the grid in every zone it is read in. A seeder, an import or a hand-built
     * payload is not bound by that — and a select that cannot show the value it holds is
     * a screen lying about what is stored.
     */
    const minutes = MINUTES.includes(minute)
        ? MINUTES
        : [...MINUTES, minute].filter(Boolean).sort();

    return (
        <div className="flex items-center gap-1">
            <Select
                value={hour === '' ? NO_TIME : hour}
                disabled={disabled}
                onValueChange={(next) =>
                    onChange(
                        next === NO_TIME
                            ? ''
                            : // Picking an hour alone is a complete answer: a delivery
                              // "at 2" means 14:00, and making somebody choose :00 from
                              // a second menu to say so is a step with one outcome.
                              `${next}:${minute === '' ? '00' : minute}`,
                    )
                }
            >
                <SelectTrigger
                    className="w-[5.5rem]"
                    aria-label={t('common.field.hour')}
                >
                    <SelectValue placeholder={t('common.field.hour')} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NO_TIME}>
                        {t('common.field.no_time')}
                    </SelectItem>
                    {HOURS.map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* A colon, not a word — nothing here to translate. i18n-allow */}
            <span className="text-muted-foreground">:</span>

            <Select
                value={minute}
                disabled={disabled || hour === ''}
                onValueChange={(next) => onChange(`${hour}:${next}`)}
            >
                <SelectTrigger
                    className="w-[5.5rem]"
                    aria-label={t('common.field.minute')}
                >
                    <SelectValue placeholder={t('common.field.minute')} />
                </SelectTrigger>
                <SelectContent>
                    {minutes.map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
