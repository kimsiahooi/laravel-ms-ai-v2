import { formatDateValue } from '@/lib/format';

/**
 * The delivery date, exactly as it was agreed.
 *
 * **Nothing here converts anything, and that is the whole design.** A promised delivery is
 * a date the business wrote down — "the 15th" — not a moment on a clock. The server stores
 * the picked value verbatim (see `PurchaseOrderRequest::expectedInstant()`) and this renders
 * it verbatim, so the same order reads as the same day to every colleague, in every
 * timezone, today and after somebody changes the workspace's timezone in settings.
 *
 * That is the rule the settings screen promises: the timezone is a display reference for
 * *timestamps* — when a receipt happened, when a count was posted — and never touches a
 * date the business chose. An earlier version rendered this on a zone, which meant the
 * setting could move a delivery by a day.
 *
 * The value arrives as `Y-m-d`, or `Y-m-d H:i` when a time was agreed. Splitting on the
 * space is the whole of telling those apart — no midnight sentinel, no inference.
 *
 * `<time dateTime>` carries the machine-readable form: a bare date, or a local datetime.
 * Neither carries an offset, because the value has none.
 */
export function ExpectedDate({ date }: { date: string }) {
    const [day, time] = date.split(' ');

    return (
        <time
            className="tabular-nums"
            dateTime={time === undefined ? day : `${day}T${time}`}
        >
            {time === undefined
                ? formatDateValue(day)
                : `${formatDateValue(day)}, ${time}`}
        </time>
    );
}
