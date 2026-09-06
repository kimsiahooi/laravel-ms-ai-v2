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

type Order = App.Data.PurchaseOrderData;
type Item = App.Data.PurchaseOrderItemData;

/**
 * What was ordered, and what it came to — the body of the document.
 *
 * **Not a `DataTable`.** That is a server-driven list that searches, sorts and pages, and
 * every one of those would be wrong here: an order's lines are read in the order they
 * were entered, and a page two hiding the last four lines of a delivery note is not a
 * document anyone can check against a pallet.
 *
 * **Every figure is the server's.** The line totals and the four totals below are read
 * off the DTO rather than recomputed — `App\Support\OrderTotals` worked them out under a
 * transaction and they are what the workspace owes. The editor's own arithmetic says out
 * loud that it is an estimate for exactly this reason; this screen is where the estimate
 * stops.
 */
export function OrderLinesTable({
    order,
    items,
}: {
    order: Order;
    items: Item[];
}) {
    const { t } = useTranslation();

    return (
        <Card className="gap-0 overflow-hidden py-0">
            {/* `Table` brings its own horizontal scroll, so five money columns scroll
                inside the card on a phone rather than taking the page with them. */}
            <Table>
                <TableHeader className="bg-muted/40">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="pl-4">
                            {t('purchase-orders.line.item')}
                        </TableHead>
                        <TableHead className="text-right">
                            {t('purchase-orders.line.quantity')}
                        </TableHead>
                        <TableHead className="text-right">
                            {t('purchase-orders.line.unit_cost')}
                        </TableHead>
                        <TableHead className="text-right">
                            {t('purchase-orders.line.discount')}
                        </TableHead>
                        <TableHead className="pr-4 text-right">
                            {t('purchase-orders.line.total')}
                        </TableHead>
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {items.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="py-3 pl-4">
                                <ItemName item={item} />
                            </TableCell>
                            <TableCell className="whitespace-nowrap py-3 text-right tabular-nums">
                                {item.quantity}
                                {item.unit !== null && (
                                    <span className="ml-1 text-muted-foreground text-xs">
                                        {t(
                                            `units.symbol.${item.unit}` as const,
                                        )}
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="whitespace-nowrap py-3 text-right tabular-nums">
                                {formatMoney(item.unit_cost, order.currency)}
                            </TableCell>
                            <TableCell className="whitespace-nowrap py-3 text-right tabular-nums">
                                <Discount
                                    item={item}
                                    currency={order.currency}
                                />
                            </TableCell>
                            <TableCell className="whitespace-nowrap py-3 pr-4 text-right font-medium tabular-nums">
                                {formatMoney(item.line_total, order.currency)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            <Totals order={order} />
        </Card>
    );
}

/**
 * What the line is for.
 *
 * The name is a snapshot the order keeps, so it survives the material being renamed and
 * outlives it being deleted — but a *force* delete takes the row and the DTO reports
 * null, and the order is still a true record of what was bought. A dash rather than a
 * broken row, the same trade the transfer list makes.
 */
function ItemName({ item }: { item: Item }) {
    return (
        <div className="min-w-0">
            <span className="block truncate font-medium">
                {/* i18n-allow */}
                {item.name ?? '—'}
            </span>
            {item.sku !== null && (
                <span className="block truncate font-mono text-muted-foreground text-xs">
                    {item.sku}
                </span>
            )}
        </div>
    );
}

/**
 * What came off this line, in the terms it was given in.
 *
 * A percentage stays a percentage rather than being resolved to an amount: "10%" is what
 * was agreed with the supplier, and it is what somebody checking the invoice is looking
 * for. The money it works out to is already in the line total beside it.
 *
 * Nothing is reformatted on the way through. `PurchaseOrderItemData` trims the stored
 * `10.0000` to `10` and rounds the money to what the currency expresses, so a second
 * opinion here could only disagree with the figures the order was saved with.
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
 * The four figures the order is settled on.
 *
 * The same four the editor previews, in the same order and under the same words — the
 * shared `orders.totals.*` keys — so a person who agreed a total on the form recognises
 * it here. The tax line names the rate it was charged at, because a rate that changed in
 * settings last month must not make an old order look wrong.
 */
function Totals({ order }: { order: Order }) {
    const { t } = useTranslation();

    const rows: [TranslationKey, string][] = [
        ['orders.totals.subtotal', order.subtotal],
        ['orders.totals.discount', order.discount_total],
        ['orders.totals.tax', order.tax_total],
        ['orders.totals.total', order.total],
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
                            {t(label, { rate: order.tax_rate })}
                        </dt>
                        <dd className="tabular-nums">
                            {formatMoney(value, order.currency)}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
