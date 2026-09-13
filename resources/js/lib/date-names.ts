import type { Translate } from '@/lib/i18n';
import type { TranslationKey } from '@/types/lang';

/**
 * The words a date is spelled with, in the language the server rendered.
 *
 * **Why this module exists at all.** `lib/format.ts` used to own two hard-coded English
 * arrays, so every date in the app read `15 Oct 2026` to a Malay or Chinese reader. The
 * obvious fix — ask `Intl` for the month name — is the one thing that file must never do:
 * ICU's names differ between the SSR runtime and the browser (CLDR 42 changed en-GB's
 * short September from "Sep" to "Sept"), so the two renders disagree on the same input and
 * produce a React #418 that names no component. The words have to come from `lang/`, where
 * every other word in this app already lives.
 *
 * **Why it is a function of a translator rather than a hook.** `scripts/check-structure.sh`
 * forbids anything under `lib/` from importing React, and rightly — these are pure
 * transforms that SSR renders concurrently. {@see Translate} is declared in `lib/i18n.ts`
 * for exactly this case, and `lib/validation/gate.ts` already takes the same seam. Both
 * imports here are `import type`, so nothing survives compilation.
 *
 * React lives one layer up, in `hooks/use-date-names.ts`, and nowhere else.
 */
export type DateNames = {
    /** January first — indexed by `month - 1`, matching `Date.getMonth()`. */
    readonly months: readonly string[];
    /** Sunday first — the order `Date.getDay()` returns. */
    readonly weekdays: readonly string[];
    /**
     * A whole date: `15 Oct 2026`, or `2026年10月15日`.
     *
     * A pattern from `lang/` rather than a template literal here, because word order is
     * not universal — English leads with the day and Chinese with the year, and
     * `docs/LOCALIZATION.md` asks for interpolation over concatenation for that reason.
     */
    readonly long: (day: number, month: string, year: number) => string;
    /** A calendar's caption: `Oct 2026`, or `2026年10月`. Same argument. */
    readonly caption: (month: string, year: number) => string;
};

/**
 * Word keys, not numbers: PHP casts a numeric string array key to an int, so `'1' => 'Jan'`
 * would flatten to `common.month.1` and make the lang file lie about its own shape.
 *
 * `satisfies` rather than a plain annotation, so each key is still checked against the
 * generated union — a renamed lang key is a tsc error here rather than twelve `common.month.jan`
 * strings rendered literally on a calendar.
 */
const MONTH_KEYS = [
    'common.month.jan',
    'common.month.feb',
    'common.month.mar',
    'common.month.apr',
    'common.month.may',
    'common.month.jun',
    'common.month.jul',
    'common.month.aug',
    'common.month.sep',
    'common.month.oct',
    'common.month.nov',
    'common.month.dec',
] as const satisfies readonly TranslationKey[];

const WEEKDAY_KEYS = [
    'common.weekday.sun',
    'common.weekday.mon',
    'common.weekday.tue',
    'common.weekday.wed',
    'common.weekday.thu',
    'common.weekday.fri',
    'common.weekday.sat',
] as const satisfies readonly TranslationKey[];

/**
 * Resolve the whole table once, from a bound translator.
 *
 * Nineteen lookups, so it is worth memoising at the hook rather than calling per cell —
 * a table of dates would otherwise resolve the same twelve month names on every row.
 */
export function dateNames(t: Translate): DateNames {
    return {
        months: MONTH_KEYS.map((key) => t(key)),
        weekdays: WEEKDAY_KEYS.map((key) => t(key)),
        long: (day, month, year) => t('common.date.long', { day, month, year }),
        caption: (month, year) => t('common.date.caption', { month, year }),
    };
}
