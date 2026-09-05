import { cn } from '@/lib/utils';

/**
 * A signed change, with the direction readable at a glance down the column.
 *
 * The ledger has no direction column — the sign *is* the direction — so this is where
 * that becomes something you can scan. An explicit `+` on the way in, because a bare
 * number beside a `-12` reads as neutral rather than positive.
 *
 * **Only the outward direction is coloured.** The preset has no success token and one
 * is not being invented here for a single cell; `destructive` for stock leaving is the
 * convention every ledger already uses, and colouring both directions would make a
 * column of alternating red and green that is harder to read than either alone.
 *
 * Zero happens — setting a level to what it already is records a movement of nothing,
 * which is a true statement about a count somebody took. It gets neither sign nor
 * emphasis.
 *
 * Nothing here formats the number: it arrives at the column's own scale as a string,
 * and turning it into a number to print it is exactly what StockService avoids.
 * `tabular-nums` lines the digits up; the sign is read aloud as "minus", so it needs no
 * extra text for a screen reader.
 */
export function QuantityCell({ quantity }: { quantity: string }) {
    const direction = Math.sign(Number(quantity));

    return (
        <span
            className={cn(
                'font-medium tabular-nums',
                direction < 0 && 'text-destructive',
                direction === 0 && 'text-muted-foreground',
            )}
        >
            {/* The + is punctuation, not a word. i18n-allow */}
            {direction > 0 ? `+${quantity}` : quantity}
        </span>
    );
}
