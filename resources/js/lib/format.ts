import type { TranslationKey } from '@/types/lang';

/**
 * Pure formatting helpers. No React, no `Date.now()`, no ambient locale, no ambient
 * time zone — every function is a deterministic transform of its arguments.
 *
 * That is deliberate. Under SSR the server and the browser render the same markup;
 * anything that reads the ambient clock or the ambient locale/zone produces two
 * different strings and a React #418 hydration mismatch, which nothing in this repo
 * would catch automatically. So "now" and "which zone" are always passed in — by a
 * caller that already knows the answer the server used.
 *
 * Timestamps are stored, and arrive, in UTC. These functions turn one into the wall
 * clock of a given IANA zone for display. Nothing here converts in the other
 * direction: nothing in this app sends a date back.
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

/** A UTC instant broken into the wall-clock fields of some zone. */
type Zoned = {
    /** 1-12, not the 0-11 the Date API uses. */
    month: number;
    day: number;
    year: number;
    hour: number;
    minute: number;
};

/**
 * One `Intl.DateTimeFormat` per zone, built once.
 *
 * Module-level state, which `lib/` otherwise avoids — but this is memoisation, not
 * configuration: the key is the whole input, so a cached formatter is the same object
 * a fresh call would build. Nothing about a request can leak through it, which is the
 * property that matters for concurrent SSR renders. Constructing one is among the
 * more expensive things in the standard library and a table renders one per row.
 *
 * The UTC substitute for an unknown zone is cached under the bad key as well, so a
 * misconfiguration costs one RangeError rather than one per cell.
 */
const FORMATTERS = new Map<string, Intl.DateTimeFormat>();

function build(timeZone: string): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat('en-US', {
        timeZone,
        // h23, not `hour12: false` — the latter renders midnight as "24" in some ICU
        // versions and "00" in others, which is exactly the kind of thing that differs
        // between the SSR runtime and a browser.
        hourCycle: 'h23',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
    });
}

function formatterFor(timeZone: string): Intl.DateTimeFormat {
    const cached = FORMATTERS.get(timeZone);

    if (cached !== undefined) {
        return cached;
    }

    let formatter: Intl.DateTimeFormat;

    try {
        formatter = build(timeZone);
    } catch {
        // An identifier this runtime does not know. TimeZones::supports() rejects those
        // server-side and useTimeZone() falls back, so reaching here means a value that
        // went round neither — a wrong zone rather than a wrong instant. Fall back to
        // the zone the data is already in: the whole column showing the right time in
        // the wrong place beats the whole column showing nothing.
        formatter = build('UTC');
    }

    FORMATTERS.set(timeZone, formatter);

    return formatter;
}

/**
 * The wall-clock fields of `iso` in `timeZone`, or null if the instant is unreadable.
 *
 * Only NUMERIC parts are read, and that is the whole trick. Asking Intl for a month
 * *name* would hand rendering to ICU, whose data differs between the SSR runtime and
 * the browser — CLDR 42 changed en-GB's short September from "Sep" to "Sept", so the
 * two sides can disagree on the same input and the mismatch is invisible until it
 * hydrates. Digits are digits in every ICU version; the month name comes from the
 * table above, which is ours. Verified identical across Node 24 and Bun 1.3.
 *
 * The conversion itself still has to be Intl's: it is the only thing that knows a zone
 * was -05:00 in December and -04:00 in June. A stored offset would be wrong twice a year.
 */
function zoned(iso: string, timeZone: string): Zoned | null {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const fields: Record<string, number> = {};

    for (const part of formatterFor(timeZone).formatToParts(date)) {
        if (part.type !== 'literal') {
            fields[part.type] = Number(part.value);
        }
    }

    return {
        month: fields.month,
        day: fields.day,
        year: fields.year,
        hour: fields.hour,
        minute: fields.minute,
    };
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** ISO-8601 → `6 Sep 2026`, on the wall clock of `timeZone`. */
export function formatDate(iso: string, timeZone: string): string {
    const parts = zoned(iso, timeZone);

    if (parts === null) {
        return '';
    }

    return `${parts.day} ${MONTHS[parts.month - 1]} ${parts.year}`;
}

/**
 * ISO-8601 → `6 Sep 2026, 02:35 (+08:00)`. For the tooltip behind a relative time.
 *
 * The offset is spelled out because the tooltip is where someone goes to resolve a
 * doubt, and "02:35" alone cannot answer "whose 02:35". It is derived from the same
 * conversion rather than stored, so it is right on both sides of a DST boundary.
 */
export function formatDateTime(iso: string, timeZone: string): string {
    const parts = zoned(iso, timeZone);

    if (parts === null) {
        return '';
    }

    const clock = `${pad(parts.hour)}:${pad(parts.minute)}`;

    return `${formatDate(iso, timeZone)}, ${clock} (${offset(iso, parts)})`;
}

/** `+08:00` — how far `parts` sits from the UTC instant it was derived from. */
function offset(iso: string, parts: Zoned): string {
    const wall = Date.UTC(
        parts.year,
        parts.month - 1,
        parts.day,
        parts.hour,
        parts.minute,
    );
    // Floored to the minute, because `parts` has no seconds to compare against.
    const instant = Math.floor(new Date(iso).getTime() / 60_000) * 60_000;
    const minutes = Math.round((wall - instant) / 60_000);
    const sign = minutes < 0 ? '-' : '+';
    const size = Math.abs(minutes);

    return `${sign}${pad(Math.floor(size / 60))}:${pad(size % 60)}`;
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
 *
 * No time zone, and none needed: this is the distance between two instants, and that
 * is the same number on every clock on earth.
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
