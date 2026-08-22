import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate, formatDateTime, relativeTime } from '@/lib/format';

/**
 * A timestamp that reads as "3d ago", with the exact time on hover.
 *
 * The first render — server and hydration alike — shows the absolute UTC date, which
 * is a pure function of the prop. The relative form needs the current clock, so it
 * only appears after mount, where the two renders can no longer disagree.
 */
export function TimeAgo({ iso }: { iso: string | null }) {
    const { tChoice } = useTranslation();
    const [relative, setRelative] = useState<string | null>(null);

    useEffect(() => {
        if (iso === null) {
            return;
        }

        // Reading the clock is safe here and nowhere else: this is inside useEffect,
        // so it never runs during render. relativeTime takes `now` as an argument
        // precisely so it can only be read from a place like this.
        const elapsed = relativeTime(iso, Date.now()); // ui-allow

        // tChoice, not t: ":count days ago" renders "1 days ago" through a plain
        // lookup. Malay and Chinese have no plural inflection, so the choice has to be
        // the locale's to make — a ternary here would be wrong in two of three.
        setRelative(elapsed ? tChoice(elapsed.key, elapsed.count) : null);
    }, [iso, tChoice]);

    if (iso === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <time dateTime={iso} title={formatDateTime(iso)}>
            {relative ?? formatDate(iso)}
        </time>
    );
}
