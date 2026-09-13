import { usePage } from '@inertiajs/react';

/**
 * The IANA zone this page is rendered in — `Asia/Kuala_Lumpur`, `UTC`, …
 *
 * **The workspace's clock, on a workspace page.** The zone set in business settings is
 * the reference every date is read against, so the same order shows the same date to the
 * buyer at the next desk and to a colleague reading from another country. The browser's
 * own zone answers only on `/admin`, where there is no business to have a clock.
 *
 * Read from the page props, never from `Intl.DateTimeFormat().resolvedOptions()`. The
 * server already formatted this page's dates against one zone and the browser has to
 * agree with it or hydration diverges — and a stored setting cannot differ between the
 * two renders at all, which a browser-reported value could.
 *
 * Falls back to UTC, matching `App\Support\TimeZones::FALLBACK`.
 */
export function useTimeZone(): string {
    return usePage().props.timezone ?? 'UTC';
}
