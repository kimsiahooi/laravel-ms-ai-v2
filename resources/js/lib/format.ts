import type { TranslationKey } from '@/types/lang';

/**
 * Pure formatting helpers. No React, no `Date.now()`, no `Intl` — every function is
 * a deterministic transform of its arguments.
 *
 * That is deliberate. Under SSR the server and the browser render the same markup;
 * anything that reads the ambient clock or the ambient locale/timezone produces two
 * different strings and a React #418 hydration mismatch, which nothing in this repo
 * would catch automatically. So "now" is always passed in by a caller that already
 * knows it is running in the browser.
 */

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

/** ISO-8601 → `12 Aug 2026`, always in UTC so both renders agree. */
export function formatDate(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return `${date.getUTCDate()} ${MONTHS[date.getUTCMonth()]} ${date.getUTCFullYear()}`;
}

/** ISO-8601 → `12 Aug 2026, 14:03 UTC`. For the tooltip behind a relative time. */
export function formatDateTime(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const hours = String(date.getUTCHours()).padStart(2, '0');
    const minutes = String(date.getUTCMinutes()).padStart(2, '0');

    return `${formatDate(iso)}, ${hours}:${minutes} UTC`;
}

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/**
 * How long ago `iso` was, as a translation key and a count — not as text.
 *
 * Returning a key keeps this file free of English and free of React while still
 * letting the caller render "3d ago" in whichever language the server chose. `null`
 * means it is older than a month, where an absolute date reads better than a count.
 *
 * `now` is a required argument rather than a `Date.now()` call: that is what makes
 * this safe to reach for from anywhere, because reading the clock stays the caller's
 * decision and can be kept out of render.
 */
export function relativeTime(
    iso: string,
    now: number,
): { key: TranslationKey; count: number } | null {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return null;
    }

    const elapsed = now - then;

    if (elapsed < MINUTE) {
        return { key: 'common.time.just_now', count: 0 };
    }

    if (elapsed < HOUR) {
        return {
            key: 'common.time.minutes_ago',
            count: Math.floor(elapsed / MINUTE),
        };
    }

    if (elapsed < DAY) {
        return {
            key: 'common.time.hours_ago',
            count: Math.floor(elapsed / HOUR),
        };
    }

    if (elapsed < 30 * DAY) {
        return {
            key: 'common.time.days_ago',
            count: Math.floor(elapsed / DAY),
        };
    }

    return null;
}
