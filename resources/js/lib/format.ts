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
 * `2h ago`, `3d ago`, or the absolute date once it is over a month old.
 *
 * `now` is a required argument, not `Date.now()`: that is what keeps this callable
 * from a render without risking a hydration mismatch.
 */
export function formatRelative(iso: string, now: number): string {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const elapsed = now - then;

    if (elapsed < MINUTE) {
        return 'just now';
    }

    if (elapsed < HOUR) {
        return `${Math.floor(elapsed / MINUTE)}m ago`;
    }

    if (elapsed < DAY) {
        return `${Math.floor(elapsed / HOUR)}h ago`;
    }

    if (elapsed < 30 * DAY) {
        return `${Math.floor(elapsed / DAY)}d ago`;
    }

    return formatDate(iso);
}
