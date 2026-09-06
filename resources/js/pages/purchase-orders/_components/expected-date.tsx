import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate } from '@/lib/format';

/**
 * The delivery date, on the reader's clock.
 *
 * `expected_date` is an instant, not a bare day: the server anchors the day somebody
 * picked to the moment it began **in the zone they picked it from**, and this renders it
 * back on whatever clock the reader is on. For the person who set it — and for everyone
 * else in the same working zone, which is the ordinary case — that is the day they chose.
 *
 * **A reader far enough west can see the day before, and that is inherent** rather than a
 * bug to route around: a calendar day held as an instant has to be read on some clock, and
 * reading it on the viewer's is the rule the rest of this app follows. The alternative is a
 * `date` column with no zone at all, which cannot be compared against `received_at` and the
 * other instants the ledger keeps.
 *
 * `<time dateTime>` carries the full instant for anything reading the page mechanically,
 * while the text is the readable form.
 */
export function ExpectedDate({ date }: { date: string }) {
    const timeZone = useTimeZone();

    return (
        <time className="tabular-nums" dateTime={date}>
            {formatDate(date, timeZone)}
        </time>
    );
}
