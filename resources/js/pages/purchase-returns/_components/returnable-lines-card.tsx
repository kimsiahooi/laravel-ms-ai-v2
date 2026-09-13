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
import { ReturnableLineRow } from '@/pages/purchase-returns/_components/returnable-line-row';

type Line = App.Data.ReturnableLineData;

/** One delivered line, with where it sits in the payload once it carries a quantity. */
export type ReturnRow = {
    line: Line;
    typed: string;
    index: number | null;
};

/**
 * Every delivered line, paired with where it will sit in the request.
 *
 * **Only rows carrying a quantity are posted, so the payload index is not the row index**, and
 * this is the one place that arithmetic happens. The same list then names the inputs, resolves
 * the error keys and builds the request — so what the input is called, what the server files a
 * failure under and what `focusFirstInvalid` looks up are one string rather than three that
 * have to agree.
 *
 * Here rather than on the page because a row is this component's idea; the page only needs the
 * result.
 */
export function returnRows(
    lines: Line[],
    quantities: Readonly<Record<string, string>>,
): ReturnRow[] {
    let position = 0;

    return lines.map((line) => {
        const typed = (
            quantities[String(line.purchase_order_item_id)] ?? ''
        ).trim();

        return { line, typed, index: typed === '' ? null : position++ };
    });
}

/**
 * Every line that still has something left, filled to its maximum.
 *
 * **Rows with nothing remaining are left alone.** `remaining` already excludes this return, so
 * a row reading zero can only be one this return holds in full — overwriting it with zero would
 * delete somebody's work and then be refused for being zero.
 *
 * Here rather than on the page because "how much may this row hold" is the rule this file
 * already owns.
 */
export function fillAllQuantities(
    lines: Line[],
    quantities: Readonly<Record<string, string>>,
): Record<string, string> {
    const next = { ...quantities };

    for (const line of lines) {
        if (line.remaining !== '0') {
            next[String(line.purchase_order_item_id)] = line.remaining;
        }
    }

    return next;
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
    return rows
        .filter((row) => row.index !== null)
        .map((row) => ({
            purchase_order_item_id: String(row.line.purchase_order_item_id),
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
    const moneyLines: MoneyLine[] = rows
        .filter((row) => row.index !== null)
        .map((row) => ({
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
