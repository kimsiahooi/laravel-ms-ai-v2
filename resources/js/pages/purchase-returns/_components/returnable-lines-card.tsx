import { OrderTotalsSummary } from '@/components/form/order-totals-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import type { MoneyLine } from '@/lib/money';
import {
    fillAllQuantities as fillAll,
    returnRows as pairRows,
    type ReturnRow as Row,
    typedRows,
} from '@/lib/returns';
import { ReturnableLineRow } from '@/pages/purchase-returns/_components/returnable-line-row';

type Line = App.Data.ReturnablePurchaseLineData;

/** One delivered line, with where it sits in the payload once it carries a quantity. */
export type ReturnRow = Row<Line>;

/** Which field on a delivered line the form posts and keys its inputs by. */
const idOf = (line: Line): number => line.purchase_order_item_id;

/**
 * Every delivered line, paired with where it will sit in the request.
 *
 * **The pairing itself lives in `lib/returns.ts`**, shared with sales returns, because the
 * payload-index-is-not-the-row-index arithmetic is the one piece of this screen that must not
 * drift between the two modules. What stays here is the only part that is this module's: which
 * field identifies a line.
 */
export function returnRows(
    lines: Line[],
    quantities: Readonly<Record<string, string>>,
): ReturnRow[] {
    return pairRows(lines, quantities, idOf);
}

/** {@see fillAll} bound to this module's line — rows with nothing left are skipped. */
export function fillAllQuantities(
    lines: Line[],
    quantities: Readonly<Record<string, string>>,
): Record<string, string> {
    return fillAll(lines, quantities, idOf);
}

/**
 * Those rows as the request sends them.
 *
 * The id is a string because that is what a form submits and what the mirror's `id()` primitive
 * checks; the server's `integer` rule accepts it.
 */
export function payloadItems(
    rows: ReturnRow[],
): { purchase_order_item_id: string; quantity: string }[] {
    return typedRows(rows).map((row) => ({
        purchase_order_item_id: String(idOf(row.line)),
        quantity: row.typed,
    }));
}

/**
 * The grid: every line of the delivery that can still send something back.
 *
 * **The order decides which rows exist, not a picker.** There is no add and no remove — a
 * return can only credit lines that were delivered, so the rows are given and the only thing a
 * person supplies is how much of each. Leaving a box empty is how a line is left off, which
 * makes "nothing on this return" and "nothing typed yet" the same gesture rather than two.
 *
 * **The rows arrive computed**, by {@link returnRows} just above — one list, used for the
 * request and for the markup, so the two cannot disagree about which row is which.
 *
 * **"Return everything" skips rows with nothing left.** A row whose remaining is zero can only
 * be one this return already holds in full, and overwriting it with zero would delete work and
 * then be refused for being zero.
 */
export function ReturnableLinesCard({
    rows,
    currency,
    taxRate,
    errors,
    onChange,
    onFillAll,
}: {
    rows: ReturnRow[];
    currency: string;
    /** A percentage: `'6'`, not `'0.06'`. The tax row quotes it back. */
    taxRate: string;
    /** The whole bag — a row reads its own key, and the list error renders underneath. */
    errors: Record<string, string>;
    onChange: (orderItemId: number, quantity: string) => void;
    onFillAll: () => void;
}) {
    const { t } = useTranslation();

    // Only the rows that carry a quantity contribute — a half-typed box is not a credit.
    const moneyLines: MoneyLine[] = typedRows(rows).map((row) => ({
        quantity: row.typed,
        unitPrice: row.line.unit_cost,
        discountType: row.line.discount_type,
        discountValue: row.line.discount_value,
        taxable: row.line.taxable,
    }));

    return (
        <Card>
            <CardContent className="space-y-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h2 className="font-medium">
                            {t('purchase-returns.lines.heading')}
                        </h2>
                        <p className="max-w-2xl text-muted-foreground text-sm">
                            {t('purchase-returns.lines.description')}
                        </p>
                    </div>

                    {/* `type="button"`, or it submits the form. */}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                        onClick={onFillAll}
                    >
                        {t('purchase-returns.lines.fill_all')}
                    </Button>
                </div>

                <InputError message={errors.items} />

                <div className="-mx-6 overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">
                                    {t('purchase-returns.line.item')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('purchase-returns.line.ordered')}
                                </TableHead>
                                <TableHead className="hidden text-right sm:table-cell">
                                    {t('purchase-returns.line.returned')}
                                </TableHead>
                                <TableHead className="text-right">
                                    {t('purchase-returns.line.remaining')}
                                </TableHead>
                                <TableHead>
                                    {t('purchase-returns.line.quantity')}
                                </TableHead>
                                <TableHead className="hidden pr-6 text-right md:table-cell">
                                    {t('purchase-returns.line.total')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <ReturnableLineRow
                                    key={row.line.purchase_order_item_id}
                                    line={row.line}
                                    value={row.typed}
                                    index={row.index}
                                    currency={currency}
                                    error={
                                        row.index === null
                                            ? undefined
                                            : (errors[
                                                  `items.${row.index}.quantity`
                                              ] ??
                                              errors[
                                                  `items.${row.index}.purchase_order_item_id`
                                              ])
                                    }
                                    onChange={(quantity) =>
                                        onChange(
                                            row.line.purchase_order_item_id,
                                            quantity,
                                        )
                                    }
                                />
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex justify-end">
                    <OrderTotalsSummary
                        lines={moneyLines}
                        taxRate={taxRate}
                        currency={currency}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
