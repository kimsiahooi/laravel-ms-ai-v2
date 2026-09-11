import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate, timeOfDay } from '@/lib/format';

/**
 * The delivery date, on the reader's clock — and the time, if one was agreed.
 *
 * `expected_date` is an instant, not a bare day: the server anchors what somebody picked
 * to the moment it began **in the zone they picked it from**, and this renders it back on
 * whatever clock the reader is on. For the person who set it — and for everyone else in
 * the same working zone, which is the ordinary case — that is what they chose.
 *
 * **Whether a time appears is inferred, not stored.** One instant cannot say whether a
 * day or a moment was picked, so midnight is read as "no time given" — see
 * {@see timeOfDay}, which is where that rule and its costs are written down. The one
 * worth knowing here: a reader outside the picker's zone can be shown a time nobody
 * agreed, because midnight there is not midnight here.
 *
 * **A reader far enough west can also see the day before, and that is inherent** rather
 * than a bug to route around: a calendar day held as an instant has to be read on some
 * clock, and reading it on the viewer's is the rule the rest of this app follows. The
 * alternative is a `date` column with no zone at all, which cannot be compared against
 * `received_at` and the other instants the ledger keeps.
 *
 * Not `formatDateTime`, which always appends `(+08:00)`: that function exists for a
 * tooltip, where the offset is the whole point of opening it. On a row of a document it
 * is noise.
 *
 * `<time dateTime>` carries the full instant for anything reading the page mechanically,
 * while the text is the readable form.
 */
export function ExpectedDate({ date }: { date: string }) {
    const timeZone = useTimeZone();
    const clock = timeOfDay(date, timeZone);
    const day = formatDate(date, timeZone);

    return (
        <time className="tabular-nums" dateTime={date}>
            {/* A comma between a date and a clock is punctuation, not a sentence, and
                reads the same in all three locales. i18n-allow */}
            {clock === null ? day : `${day}, ${clock}`}
        </time>
    );
}
