import { useEffect, useState } from 'react';
import { formatDate, formatDateTime, formatRelative } from '@/lib/format';

/**
 * A timestamp that reads as "3d ago", with the exact time on hover.
 *
 * The first render — server and hydration alike — shows the absolute UTC date, which
 * is a pure function of the prop. The relative form needs the current clock, so it
 * only appears after mount, where the two renders can no longer disagree.
 */
export function TimeAgo({ iso }: { iso: string | null }) {
    const [relative, setRelative] = useState<string | null>(null);

    useEffect(() => {
        if (iso === null) {
            return;
        }

        // Reading the clock is safe here and nowhere else: this is inside useEffect,
        // so it never runs during render. formatRelative takes `now` as an argument
        // precisely so it can only be read from a place like this.
        setRelative(formatRelative(iso, Date.now())); // ui-allow
    }, [iso]);

    if (iso === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <time dateTime={iso} title={formatDateTime(iso)}>
            {relative ?? formatDate(iso)}
        </time>
    );
}
