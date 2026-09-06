import { TriangleAlert } from 'lucide-react';

/**
 * How many items in this warehouse have reached their reorder level.
 *
 * **Amber text and an icon, not a badge and not a background.** It is the same signal
 * the warehouse's own summary card gives, in the same colour, for the same number —
 * somebody who has seen one should recognise the other without being told they are
 * related. A badge would be a third piece of vocabulary for one fact.
 *
 * **Zero is shown, not hidden.** A blank cell reads as "not calculated", and on a list
 * whose purpose is finding the warehouse that needs a person, "nothing to do here" is
 * an answer worth printing. It is muted, so a column of them stays quiet and the row
 * that is not zero is the one the eye lands on.
 *
 * **No accessible label, deliberately.** A screen reader announces a cell with its
 * column header, so this is already heard as "Needs reorder, 3" — and a sentence saying
 * the same thing again on every row is noise, not access. The icon is hidden from it
 * for the same reason: it repeats the colour's job, and the colour was never the only
 * channel.
 */
export function ReorderCountCell({ count }: { count: number }) {
    if (count === 0) {
        return <span className="text-muted-foreground tabular-nums">0</span>;
    }

    return (
        <span className="inline-flex items-center gap-1.5 font-medium text-chart-3 tabular-nums">
            <TriangleAlert className="size-3.5 shrink-0" aria-hidden />
            {count}
        </span>
    );
}
