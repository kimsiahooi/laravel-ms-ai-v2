import { DecimalCell } from '@/components/form/decimal-cell';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import { orderTotals } from '@/lib/money';

type Line = App.Data.ReturnableLineData;

/**
 * One delivered line, and how much of it is going back.
 *
 * **Three figures before the box, and they are the reason this is a table and not a picker.**
 * Delivered, already returned, and what is left: somebody deciding a quantity is deciding it
 * against those three, and asking them to hold the third in their head while typing is how a
 * return ends up refused a round trip later.
 *
 * **The input's `name` carries the payload index, not the row index**, because only rows with a
 * typed quantity are posted. `index` is null while the box is empty, and the row then renders no
 * inputs at all — which is precisely what "this line is not on the return" means on the wire.
 * The error key the page reads is built from the same number, so the input's name, the server's
 * field and what `focusFirstInvalid` looks up are one string.
 *
 * `DecimalCell` rather than a `TextField`: it is controlled, because the running credit above
 * redraws as somebody types and a total cannot be read back out of the DOM.
 */
export function ReturnableLineRow({
    line,
    value,
    index,
    error,
    currency,
    onChange,
}: {
    line: Line;
    /** What is typed for this line. Empty means the line is not on the return. */
    value: string;
    /** Where this row sits in the payload, or null while it carries no quantity. */
    index: number | null;
    error?: string;
    currency: string;
    onChange: (quantity: string) => void;
}) {
    const { t } = useTranslation();

    const unit =
        line.unit === null ? null : t(`units.symbol.${line.unit}` as const);

    // Only once there is a quantity: a blank row credits nothing, and a row of zeroes down
    // the column would read as a document that had been priced.
    //
    // Through `orderTotals` rather than `lineAmounts`, exactly as the order editor's own row
    // does: `lineAmounts()` answers at the working scale — four places, what a line is stored
    // at — and `MYR 7.0000` beside a total reading `MYR 7.00` is two answers to one question.
    // One line taxed at nothing is that same net, rounded by the function that rounds the
    // totals underneath it.
    const credit =
        index === null
            ? null
            : orderTotals(
                  [
                      {
                          quantity: value,
                          unitPrice: line.unit_cost,
                          discountType: line.discount_type,
                          discountValue: line.discount_value,
                          taxable: line.taxable,
                      },
                  ],
                  '0',
              ).subtotal;

    return (
        <TableRow>
            <TableCell className="max-w-[16rem] align-top">
                <span className="block truncate font-medium">
                    {/* Null only after a hard delete of the material; what was paid for
                        it is still on the line. i18n-allow */}
                    {line.name ?? '—'}
                </span>
                {line.sku !== null && (
                    <span className="block truncate text-muted-foreground text-xs">
                        {line.sku}
                    </span>
                )}
            </TableCell>

            <Figure value={line.ordered} unit={unit} />
            <Figure value={line.returned} muted hideBelow="sm" />
            <Figure value={line.remaining} />

            <TableCell className="w-36 align-top">
                {index === null ? (
                    // No hidden id either: a line with no quantity is simply not on the
                    // document, and sending it would make the server refuse a blank.
                    <DecimalCell
                        name=""
                        label="purchase-returns.line.quantity"
                        placeholder="purchase-returns.line.quantity_placeholder"
                        value={value}
                        onChange={onChange}
                    />
                ) : (
                    <>
                        <input
                            type="hidden"
                            name={`items[${index}][purchase_order_item_id]`}
                            value={line.purchase_order_item_id}
                        />
                        <DecimalCell
                            name={`items[${index}][quantity]`}
                            label="purchase-returns.line.quantity"
                            placeholder="purchase-returns.line.quantity_placeholder"
                            value={value}
                            onChange={onChange}
                            error={error}
                        />
                    </>
                )}
            </TableCell>

            <TableCell className="hidden text-right align-top tabular-nums md:table-cell">
                {credit === null ? (
                    // i18n-allow
                    <span className="text-muted-foreground">—</span>
                ) : (
                    formatMoney(credit, currency)
                )}
            </TableCell>
        </TableRow>
    );
}

/** One right-aligned quantity, with the unit only where it earns its width. */
function Figure({
    value,
    unit,
    muted,
    hideBelow,
}: {
    value: string;
    unit?: string | null;
    muted?: boolean;
    hideBelow?: 'sm';
}) {
    return (
        <TableCell
            className={[
                'text-right align-top tabular-nums',
                muted ? 'text-muted-foreground' : '',
                hideBelow === 'sm' ? 'hidden sm:table-cell' : '',
            ]
                .filter(Boolean)
                .join(' ')}
        >
            {value}
            {unit && (
                <span className="ml-1 text-muted-foreground text-xs">
                    {unit}
                </span>
            )}
        </TableCell>
    );
}
