import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import type { TranslationKey } from '@/types/lang';

/**
 * A stored money amount in a table cell, or a word when nobody has set one.
 *
 * It exists for the same mechanical reason {@see DateCell} does — a TanStack `cell`
 * renderer is called as a plain function rather than mounted, so it cannot call `t()`
 * itself — and it arrives shared rather than per-module because both catalogue lists
 * need it in the same change. That is two concrete callers, not a guess at a third: the
 * rule this codebase follows is against inventing an abstraction from one example, and
 * the alternative here was writing the same twelve lines twice and watching them drift.
 *
 * **Empty is a word, not a dash.** Everywhere else in this app an em dash means "nothing
 * to show". A missing price is not nothing — it is a decision somebody has not made, and
 * a dash in a column of numbers reads as zero. The caller passes the sentence, because
 * what an unset amount *means* differs by column: a material with no cost has never been
 * priced, while an order with no discount was simply sold at list.
 *
 * The currency travels with the figure rather than sitting once in the heading, matching
 * the order lists — see `formatMoney` on why the code and not the symbol.
 */
export function AmountCell({
    amount,
    currency,
    scale,
    empty,
}: {
    /** A decimal string as the server stored it, or null when unset. */
    amount: string | null;
    /** ISO 4217 code the amount is quoted in. */
    currency: string;
    /**
     * Fewest decimal places to show — the currency's own scale, from the server.
     *
     * A catalogue figure is stored at four places and trimmed, so without this a column
     * reads `MYR 12.5` on one row and `MYR 8.7555` on the next. Padding is a floor and
     * never a rounding: a material genuinely priced at four places keeps all four.
     */
    scale?: number;
    /** What to say instead when `amount` is null. */
    empty: TranslationKey;
}) {
    const { t } = useTranslation();

    if (amount === null) {
        return (
            <span className="text-muted-foreground text-xs">{t(empty)}</span>
        );
    }

    return (
        <span className="whitespace-nowrap tabular-nums">
            {formatMoney(amount, currency, scale)}
        </span>
    );
}
