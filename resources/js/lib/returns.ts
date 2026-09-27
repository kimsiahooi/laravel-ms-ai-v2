/**
 * The arithmetic both return forms do, and the one piece of it that must not drift.
 *
 * **Only rows carrying a quantity are posted, so the payload index is not the row index.** That
 * mismatch is the single real trap on these screens: the input's `name`, the key Laravel files a
 * failure under, and the key `focusFirstInvalid` resolves have to be one string rather than three
 * that happen to agree. Computing the pairing once, here, is what makes them one string — and
 * putting it in `lib/` rather than in either module's `_components/` is what stops the purchase
 * side and the sales side drifting apart the first time somebody fixes one of them.
 *
 * Generic over the line, because the two modules' line shapes differ in exactly the fields the
 * form posts — `purchase_order_item_id` against `sales_order_item_id`, a unit cost against a unit
 * price. Everything the pairing needs is reached through `idOf`, so neither of those names
 * appears here.
 *
 * No JSX and no React import: the rules in `docs/ARCHITECTURE.md` put pure logic in `lib/`, and
 * `check-structure.sh` enforces it.
 */

/** One offered line, with where it sits in the payload once it carries a quantity. */
export type ReturnRow<TLine> = {
    line: TLine;
    /** What the person typed, trimmed. Empty means the line is off the return. */
    typed: string;
    /** Its position in the request, or null while the box is empty. */
    index: number | null;
};

/**
 * Every offered line, paired with where it will sit in the request.
 *
 * The same list then names the inputs, resolves the error keys and builds the request.
 */
export function returnRows<TLine>(
    lines: TLine[],
    quantities: Readonly<Record<string, string>>,
    idOf: (line: TLine) => number,
): ReturnRow<TLine>[] {
    let position = 0;

    return lines.map((line) => {
        const typed = (quantities[String(idOf(line))] ?? '').trim();

        return { line, typed, index: typed === '' ? null : position++ };
    });
}

/**
 * Every line that still has something left, filled to its maximum.
 *
 * **Rows with nothing remaining are left alone.** `remaining` already excludes the return being
 * edited, so a row reading zero can only be one this return holds in full — overwriting it with
 * zero would delete somebody's work and then be refused for being zero.
 */
export function fillAllQuantities<TLine extends { remaining: string }>(
    lines: TLine[],
    quantities: Readonly<Record<string, string>>,
    idOf: (line: TLine) => number,
): Record<string, string> {
    const next = { ...quantities };

    for (const line of lines) {
        if (line.remaining !== '0') {
            next[String(idOf(line))] = line.remaining;
        }
    }

    return next;
}

/**
 * Just the rows that are going, in payload order.
 *
 * Each module maps these into its own field name, because that name is the one thing about a row
 * the server cares about and the one thing these two documents genuinely disagree on.
 */
export function typedRows<TLine>(rows: ReturnRow<TLine>[]): ReturnRow<TLine>[] {
    return rows.filter((row) => row.index !== null);
}
