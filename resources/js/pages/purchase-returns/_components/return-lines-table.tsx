import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

type Return = App.Data.PurchaseReturnData;
type Item = App.Data.PurchaseReturnItemData;

/**
 * What is going back, and what it credits — the saved document, read-only.
 *
 * **Unpaginated, like the order's own lines.** A return has as many rows as the delivery had
 * and a page break through a credit note is a document somebody has to reassemble.
 *
 * **"2 of the 5 delivered" is the whole story of a return line**, so the delivered quantity
 * rides beside the returned one rather than living on the order in another tab. It is context,
 * not arithmetic — what may still be returned is a question about documents that do not exist
 * yet, and it belongs on the form.
 *
 * Nothing is reformatted on the way through: `PurchaseReturnItemData` already trimmed what
 * belongs in an input and rounded what is money, so a second opinion here could only disagree
 * with the figures the return was saved with.
 */
export function ReturnLinesTable({
    row,
    items,
}: {
    row: Return;
    items: Item[];
}) {
    const { t } = useTranslation();

    return (
        <Card className="overflow-hidden p-0">
            <div className="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>
                                {t('purchase-returns.line.item')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('purchase-returns.line.ordered')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('purchase-returns.line.quantity')}
                            </TableHead>
                            <TableHead className="hidden text-right sm:table-cell">
                                {t('purchase-returns.line.unit_cost')}
                            </TableHead>
                            <TableHead className="hidden text-right md:table-cell">
                                {t('purchase-returns.line.discount')}
                            </TableHead>
                            <TableHead className="text-right">
                                {t('purchase-returns.line.total')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {items.map((item) => (
                            <TableRow key={item.id}>
                                <TableCell className="max-w-[18rem]">
                                    <span className="block truncate font-medium">
                                        {/* Null only after a hard delete of the
                                            material; the money is still true. i18n-allow */}
                                        {item.name ?? '—'}
                                    </span>
                                    {item.sku !== null && (
                                        <span className="block truncate text-muted-foreground text-xs">
                                            {item.sku}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                    {item.ordered}
                                </TableCell>
                                <TableCell className="text-right font-medium tabular-nums">
                                    {item.quantity}
                                    {item.unit !== null && (
                                        <span className="ml-1 text-muted-foreground text-xs">
                                            {t(
                                                `units.symbol.${item.unit}` as const,
                                            )}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="hidden text-right tabular-nums sm:table-cell">
                                    {formatMoney(item.unit_cost, row.currency)}
                                </TableCell>
                                <TableCell className="hidden text-right tabular-nums md:table-cell">
                                    <Discount
                                        item={item}
                                        currency={row.currency}
                                    />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {formatMoney(item.line_total, row.currency)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <Totals row={row} />
        </Card>
    );
}

/**
 * What was taken off the line, in the terms it was agreed in.
 *
 * A percentage stays a percentage: it is what was agreed with the supplier, and the money it
 * works out to is already in the line credit beside it.
 */
function Discount({ item, currency }: { item: Item; currency: string }) {
    if (item.discount_type === 'none') {
        // i18n-allow
        return <span className="text-muted-foreground">—</span>;
    }

    if (item.discount_type === 'percent') {
        return <span>{`${item.discount_value}%`}</span>;
    }

    return <span>{formatMoney(item.discount_value, currency)}</span>;
}

/**
 * The four figures the credit comes to.
 *
 * The same four the form previewed, in the same order and under the same shared
 * `orders.totals.*` words, so a person who watched the figure while typing recognises it here.
 * The tax line names the rate it was charged at — the *order's* rate, copied when the return
 * was raised, so a rate changed in settings last month cannot make an old credit look wrong.
 */
function Totals({ row }: { row: Return }) {
    const { t } = useTranslation();

    const rows: [TranslationKey, string][] = [
        ['orders.totals.subtotal', row.subtotal],
        ['orders.totals.discount', row.discount_total],
        ['orders.totals.tax', row.tax_total],
        ['orders.totals.total', row.total],
    ];

    return (
        <div className="flex justify-end border-t p-4">
            <dl className="w-full space-y-1 text-sm sm:w-72">
                {rows.map(([label, value], index) => (
                    <div
                        key={label}
                        className={cn(
                            'flex justify-between gap-4',
                            index === rows.length - 1 &&
                                'border-t pt-2 font-medium',
                        )}
                    >
                        <dt className="text-muted-foreground">
                            {t(label, { rate: row.tax_rate })}
                        </dt>
                        <dd className="tabular-nums">
                            {formatMoney(value, row.currency)}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
