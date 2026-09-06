import { Plus } from 'lucide-react';
import { COLUMNS, OrderLineRow } from '@/components/form/order-line-row';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import type { DiscountType } from '@/lib/money';
import { orderTotals } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/**
 * A row while it is being edited.
 *
 * `key` is identity and never the array index: removing the second of five rows shifts
 * every index below it, and a React key that shifts remounts the picker underneath
 * holding somebody else's value. Everything else is a string because everything else is
 * an input — this is `MoneyLine` with a name and an identity.
 */
export type OrderLine = {
    key: number;
    /** A {@see StockPickerEntry} value — `raw_material:5`, not a bare id. */
    item: string;
    quantity: string;
    unitPrice: string;
    discountType: DiscountType;
    discountValue: string;
    taxable: boolean;
};

/** The columns after the item, which is headed by whatever the module calls its goods. */
const HEADINGS: TranslationKey[] = [
    'orders.line.quantity',
    'orders.line.unit_price',
    'orders.line.discount',
    'orders.line.taxable',
    'orders.line.amount',
];

/**
 * The lines of an order: what is being bought or sold, at what price, and what the whole
 * thing comes to.
 *
 * Here rather than in one module's `_components/` because purchase orders and sales
 * orders differ in the word for the counterparty, not in how a line is priced.
 *
 * **Controlled, where the rest of this folder is uncontrolled — and that is the point.**
 * Every other field seeds itself from `defaultValue` and lets the DOM hold the value,
 * which works because a dialog throws its content away on close and nobody reads a value
 * back. This one has to read them back: the totals redraw as somebody types, and a
 * running figure cannot be computed from numbers only the DOM knows. So the parent owns
 * the array and every keystroke asks it for a new one. The inputs still carry `name`s,
 * so the form posts the way every other form does — state and wire agree because the
 * wire is rendered from the state.
 *
 * **The arithmetic is `lib/money.ts` and nothing else.** That file mirrors
 * `App\Support\OrderTotals` scale for scale and rounding rule for rounding rule, so what
 * is on screen is what gets stored. A second opinion about 10% of a line — even a
 * correct-looking one — is the bug the mirror exists to prevent, and it would surface on
 * the one invoice somebody checks by hand. No total is ever posted: the server works it
 * out again from the lines, which is why the figures here can say they are an estimate.
 */
export function OrderLinesField({
    lines,
    onChange,
    entries,
    errors,
    currency,
    taxRate,
    itemLabel,
}: {
    lines: OrderLine[];
    onChange: (lines: OrderLine[]) => void;
    entries: StockPickerEntry[];
    /** Keyed `items.0.quantity`, as Laravel and the zod gate both key it. */
    errors: Record<string, string>;
    currency: string;
    /** A percentage: `'6'`, not `'0.06'`. */
    taxRate: string;
    /** `orders.line.item`, unless a module has its own word for what it sells. */
    itemLabel: TranslationKey;
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

    const add = () =>
        onChange([
            ...lines,
            {
                // Highest key seen plus one — not a ref, which would not survive the
                // parent replacing the array, and not a clock, which reads differently
                // on the server and in the browser.
                key: lines.reduce((top, l) => Math.max(top, l.key), -1) + 1,
                item: '',
                quantity: '',
                unitPrice: '',
                discountType: 'none',
                discountValue: '',
                // Taxable unless told otherwise: most lines are, and the exempt one is
                // the exception somebody remembers to untick.
                taxable: true,
            },
        ]);

    const replace = (key: number, next: OrderLine) =>
        onChange(lines.map((line) => (line.key === key ? next : line)));

    const remove = (key: number) =>
        onChange(lines.filter((line) => line.key !== key));

    return (
        <div className="space-y-4">
            {/*
                Column headers from `sm` up. Below that each line stacks and carries its
                own labels — the `sm:sr-only` on every field is what hands the naming
                back to the label once the header disappears.
            */}
            <div className={cn('hidden gap-3 sm:grid', COLUMNS)}>
                {[itemLabel, ...HEADINGS].map((heading, index) => (
                    <span
                        key={heading}
                        className={cn(
                            'font-medium text-muted-foreground text-xs',
                            index === HEADINGS.length && 'text-right',
                        )}
                    >
                        {t(heading)}
                    </span>
                ))}
                <span />
            </div>

            {/* Removing the last line is allowed — an order being started from nothing
                and an order being emptied out look the same, and both want telling how
                to put a line back. */}
            {lines.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('orders.lines.empty')}
                </p>
            ) : (
                <ul className="space-y-4 sm:space-y-3">
                    {lines.map((line, index) => (
                        <OrderLineRow
                            key={line.key}
                            line={line}
                            index={index}
                            entries={entries}
                            errors={errors}
                            currency={currency}
                            itemLabel={itemLabel}
                            onChange={(next) => replace(line.key, next)}
                            onRemove={() => remove(line.key)}
                        />
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap items-start justify-between gap-4">
                <Button type="button" variant="outline" onClick={add}>
                    <Plus className="size-4" />
                    {t('orders.lines.add')}
                </Button>

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
            </div>
        </div>
    );
}
