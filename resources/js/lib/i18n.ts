import type { TranslationKey } from '@/types/lang';

/**
 * The translation lookup itself: pure functions over a message bundle.
 *
 * No module state, no IO, no React — `translate(messages, key)` is a function of its
 * arguments and nothing else. That is what makes concurrent SSR renders safe: there
 * is no "current locale" for one request to set and another to read.
 */

export type Messages = Record<string, string>;

export type TranslationParams = Record<string, string | number>;

/**
 * The bound lookups a component holds. Declared here rather than beside the hook so
 * that pure modules under `lib/` can accept a translator without importing React —
 * `lib/validation/gate.ts` is the one that needs it.
 */
export type Translate = (
    key: TranslationKey,
    params?: TranslationParams,
) => string;

export type TranslateChoice = (
    key: TranslationKey,
    count: number,
    params?: TranslationParams,
) => string;

/**
 * Look up `key` and fill in its `:placeholders`.
 *
 * A missing key returns the key itself rather than an empty string — a visible
 * `console.overview.heading` on the page is a bug report; a blank space is a mystery.
 * `bun run check:i18n` and the generated key union should catch it long before here.
 */
export function translate(
    messages: Messages,
    key: TranslationKey,
    params?: TranslationParams,
): string {
    const message = messages[key];

    if (message === undefined) {
        return key;
    }

    return params ? interpolate(message, params) : message;
}

/**
 * The plural-aware lookup, matching Laravel's `trans_choice`.
 *
 * Segments are separated by `|`, and a segment may carry an explicit condition —
 * `{0} None|[1,19] Some|[20,*] Many`. Without conditions the segment is picked by the
 * locale's plural rule, which is why this takes the locale: English has two forms,
 * Malay and Chinese have one, so a hand-written `count === 1 ? a : b` at the call site
 * would produce wrong output in two of the three languages we ship.
 */
export function translateChoice(
    messages: Messages,
    locale: string,
    key: TranslationKey,
    count: number,
    params?: TranslationParams,
): string {
    const message = messages[key];

    if (message === undefined) {
        return key;
    }

    const segments = message.split('|');
    const chosen =
        matchExplicit(segments, count) ??
        segments[pluralIndex(locale, count)] ??
        segments[0];

    return interpolate(chosen.trim(), { count, ...params });
}

/** `{0} …` and `[1,19] …` conditions, which win over the locale's plural rule. */
function matchExplicit(segments: string[], count: number): string | null {
    for (const segment of segments) {
        const exact = segment.match(/^\s*\{\s*(-?\d+)\s*\}(.*)/s);

        if (exact && Number(exact[1]) === count) {
            return exact[2];
        }

        const range = segment.match(
            /^\s*\[\s*(-?\d+)\s*,\s*(-?\d+|\*)\s*\](.*)/s,
        );

        if (range) {
            const from = Number(range[1]);
            const to =
                range[2] === '*' ? Number.POSITIVE_INFINITY : Number(range[2]);

            if (count >= from && count <= to) {
                return range[3];
            }
        }
    }

    return null;
}

/**
 * Which segment a locale uses for `count`, mirroring Laravel's MessageSelector for
 * the locales this app ships. Malay and Chinese have no plural inflection, so they
 * always take the first (and only) segment.
 */
function pluralIndex(locale: string, count: number): number {
    switch (locale) {
        case 'ms':
        case 'zh_Hans':
            return 0;
        default:
            return count === 1 ? 0 : 1;
    }
}

/**
 * Replace `:name` placeholders.
 *
 * Longest name first, exactly as Laravel's translator sorts its replacements: with
 * `:to` and `:total` both in play, replacing the short one first would corrupt the
 * long one into `<to>tal`.
 */
function interpolate(message: string, params: TranslationParams): string {
    return Object.keys(params)
        .sort((a, b) => b.length - a.length)
        .reduce(
            (out, name) => out.split(`:${name}`).join(String(params[name])),
            message,
        );
}
