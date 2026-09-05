/**
 * Whatever somebody wrote down when they recorded the movement.
 *
 * **Off by default, and that is the whole reason the Columns panel exists.** A note runs
 * to a thousand characters, and a column of prose beside five columns of numbers takes
 * the width from the fields that carry the record. But the search box already matches on
 * notes — so leaving the column out entirely meant a row could match text that was
 * nowhere on screen. Hidden-until-asked-for is the honest answer to both.
 *
 * Truncated to the column, with the full text on `title` for the one row somebody is
 * actually reading. A tooltip would be better and would also mean 25 of them mounted per
 * page; the native affordance costs nothing and says the same thing.
 */
export function NotesCell({ notes }: { notes: string | null }) {
    if (notes === null || notes === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="block truncate text-muted-foreground" title={notes}>
            {notes}
        </span>
    );
}
