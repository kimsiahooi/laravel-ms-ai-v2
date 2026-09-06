/**
 * Whatever somebody wrote down.
 *
 * **Off by default, and that is the whole reason the Columns panel exists.** A note runs
 * to a thousand characters, and a column of prose beside five columns of numbers takes
 * the width from the fields that carry the record. But every list that stores a note also
 * *searches* it — so leaving the column out entirely meant a row could match text that
 * was nowhere on screen, which is the defect v1 shipped on the ledger and this codebase
 * has now had to fix three times. Hidden-until-asked-for is the honest answer to both.
 *
 * Truncated to the column, with the full text on `title` for the one row somebody is
 * actually reading. A tooltip would be better and would also mean 25 of them mounted per
 * page; the native affordance costs nothing and says the same thing.
 *
 * Promoted here from stock movements' `_components/` when stock takes became the third
 * consumer — the rule of three, and the two copies that existed by then were identical
 * line for line.
 */
export function NotesCell({ notes }: { notes: string | null }) {
    if (notes === null || notes === '') {
        // i18n-allow
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="block truncate text-muted-foreground" title={notes}>
            {notes}
        </span>
    );
}
