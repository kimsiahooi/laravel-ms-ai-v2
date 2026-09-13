import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import type { MoneyLine } from '@/lib/money';
import { orderTotals } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/**
 * What a document comes to, previewed as somebody types.
 *
 * **A preview, and it says so.** `lib/money.ts` follows `App\Support\OrderTotals` scale for
 * scale and rounding rule for rounding rule, so the figure here is the figure that will be
 * stored — but no total is ever posted, and the note underneath is there so nobody reconciles
 * against a number the server has not agreed to yet.
 *
 * **It takes lines rather than totals**, so the arithmetic happens in one place for every
 * caller. Handing it four finished strings would let a second caller compute them slightly
 * differently, which is the whole failure `money.ts` exists to prevent.
 *
 * Extracted from {@link OrderLinesField} when returns became a third consumer: an order's line
 * editor and a return's are different controls — one picks from a catalogue, the other from a
 * document — but what the document comes to is the same four rows underneath both.
 *
 * The caller owns where this sits; this owns only the block.
 */
export function OrderTotalsSummary({
    lines,
    taxRate,
    currency,
}: {
    /** Every line, including half-typed ones — an unfinished row contributes nothing. */
    lines: MoneyLine[];
    /** A percentage: `'6'`, not `'0.06'`. The tax row quotes it back. */
    taxRate: string;
    currency: string;
}) {
    const { t } = useTranslation();
    const totals = orderTotals(lines, taxRate);

    // Every row is handed the rate; only the tax line's wording has a `:rate` in it.
    const summary: [TranslationKey, string][] = [
        ['orders.totals.subtotal', totals.subtotal],
        ['orders.totals.discount', totals.discountTotal],
        ['orders.totals.tax', totals.taxTotal],
        ['orders.totals.total', totals.total],
    ];

    return (
        <div className="w-full sm:w-72">
            <dl className="space-y-1 text-sm">
                {summary.map(([label, value], index) => (
                    <div
                        key={label}
                        className={cn(
                            'flex justify-between gap-4',
                            index === summary.length - 1 &&
                                'border-t pt-2 font-medium',
                        )}
                    >
                        <dt className="text-muted-foreground">
                            {t(label, { rate: taxRate })}
                        </dt>
                        <dd className="tabular-nums">
                            {formatMoney(value, currency)}
                        </dd>
                    </div>
                ))}
            </dl>
            <p className="mt-2 text-muted-foreground text-xs">
                {t('orders.totals.estimate')}
            </p>
        </div>
    );
}
