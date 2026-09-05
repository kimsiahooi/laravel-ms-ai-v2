import { useTimeZone } from '@/hooks/use-time-zone';
import { formatDate } from '@/lib/format';

/**
 * A stored timestamp, shown as the date it was on the viewer's own clock.
 *
 * It exists because `formatDate` needs a zone and a TanStack `cell` renderer is called
 * as a plain function, not mounted as a component — so it cannot call a hook. Wrapping
 * the two lines in a real component is what gives the zone somewhere to come from, and
 * it keeps five column definitions from each repeating the same span.
 *
 * `<time dateTime>` carries the untranslated UTC instant for anything reading the page
 * mechanically, while the text is local. `tabular-nums` keeps a column of dates from
 * shifting as the digits change.
 */
export function DateCell({ iso }: { iso: string }) {
    const timeZone = useTimeZone();

    return (
        <time className="text-muted-foreground tabular-nums" dateTime={iso}>
            {formatDate(iso, timeZone)}
        </time>
    );
}
