import { useDateNames } from '@/hooks/use-date-names';
import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate } from '@/lib/format';

/**
 * A stored timestamp, shown as the date it was on the workspace's clock.
 *
 * It exists because `formatDate` needs a zone and a TanStack `cell` renderer is called
 * as a plain function, not mounted as a component — so it cannot call a hook. Wrapping
 * the two lines in a real component is what gives the zone somewhere to come from, and
 * it keeps five column definitions from each repeating the same span.
 *
 * `<time dateTime>` carries the untranslated UTC instant for anything reading the page
 * mechanically, while the text is on the zone business settings names — see
 * {@see useTimeZone}. `tabular-nums` keeps a column of dates from shifting as the
 * digits change.
 */
export function DateCell({ iso }: { iso: string }) {
    const timeZone = useTimeZone();
    const names = useDateNames();

    return (
        <time className="text-muted-foreground tabular-nums" dateTime={iso}>
            {formatDate(iso, timeZone, names)}
        </time>
    );
}
