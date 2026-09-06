import { Trash2 } from 'lucide-react';
import { useId } from 'react';
import { DecimalCell } from '@/components/form/decimal-cell';
import type { OrderLine } from '@/components/form/order-lines-field';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import { StockPickerField } from '@/components/form/stock-picker-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format';
import { type DiscountType, orderTotals } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/**
 * The seven columns, borrowed by the header above so the two cannot drift apart.
 * Repeated per row rather than lifted into a `<table>`, because a table row cannot
 * reflow into a stack on a phone and this one has to.
 */
export const COLUMNS =
    'sm:grid-cols-[minmax(0,1.6fr)_minmax(3.5rem,0.5fr)_minmax(4.5rem,0.7fr)_minmax(11rem,1.3fr)_2.5rem_minmax(5.5rem,0.8fr)_2.25rem]';

/** A bordered card on a phone; a bare row of cells once the header appears above it. */
const ROW =
    'grid gap-3 rounded-md border p-3 sm:items-start sm:rounded-none sm:border-0 sm:p-0';

const DISCOUNT_TYPES = ['none', 'percent', 'amount'] as const;

/**
 * One line's controls.
 *
 * Split out of {@see OrderLinesField} so each row owns its `useId`s, and so that which
 * line changed and which line went is settled in one place rather than threaded through
 * six handlers. A change hands back a whole line; the parent swaps it in by `key`,
 * never by index. Exported only so that file can reach it — nothing else should.
 */
export function OrderLineRow({
    line,
    index,
    entries,
    errors,
    currency,
    itemLabel,
    onChange,
    onRemove,
}: {
    line: OrderLine;
    index: number;
    entries: StockPickerEntry[];
    errors: Record<string, string>;
    currency: string;
    itemLabel: TranslationKey;
    onChange: (line: OrderLine) => void;
    onRemove: () => void;
}) {
    const { t } = useTranslation();
    const discountId = useId();
    const taxableId = useId();

    const set = (patch: Partial<OrderLine>) => onChange({ ...line, ...patch });
    const name = (field: string) => `items[${index}][${field}]`;
    const error = (field: string) => errors[`items.${index}.${field}`];

    // The wire value for a control Radix renders as something other than an input —
    // and for the checkbox, which posts nothing at all when it is left unticked, so
    // the server would read "not taxable" as a missing field rather than as a false.
    const hidden = (field: string, value: string) => (
        <input type="hidden" name={name(field)} value={value} />
    );

    // The value is cleared along with the type, so switching to "none" and back cannot
    // leave a figure behind that the box showing it has stopped showing.
    const setDiscountType = (value: string) =>
        set({
            discountType: value as DiscountType,
            discountValue: value === 'none' ? '' : line.discountValue,
        });

    return (
        <li className={cn(ROW, COLUMNS)}>
            {/* Seeded once and then left alone — the picker keeps its own value and
                reports it, which is safe only because `line.key` keeps this row's DOM
                node identical across an insert or a removal above it. */}
            <StockPickerField
                name={name('item')}
                label={itemLabel}
                labelHidden="sm"
                entries={entries}
                defaultValue={line.item}
                onChange={(item) => set({ item })}
                error={error('item')}
                placeholder="orders.line.item_placeholder"
                searchPlaceholder="orders.line.item_search"
                emptyMessage="orders.line.item_empty"
            />

            <DecimalCell
                name={name('quantity')}
                label="orders.line.quantity"
                placeholder="orders.line.quantity_placeholder"
                value={line.quantity}
                onChange={(quantity) => set({ quantity })}
                error={error('quantity')}
            />

            <DecimalCell
                name={name('unit_price')}
                label="orders.line.unit_price"
                placeholder="orders.line.unit_price_placeholder"
                value={line.unitPrice}
                onChange={(unitPrice) => set({ unitPrice })}
                error={error('unit_price')}
            />

            <div className="space-y-2">
                <Label htmlFor={discountId} className="sm:sr-only">
                    {t('orders.line.discount')}
                </Label>

                <div className="flex gap-2">
                    <Select
                        value={line.discountType}
                        onValueChange={setDiscountType}
                    >
                        <SelectTrigger
                            id={discountId}
                            className="w-full"
                            aria-invalid={!!error('discount_type')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {DISCOUNT_TYPES.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {t(`orders.discount_type.${type}` as const)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hidden('discount_type', line.discountType)}

                    {line.discountType === 'none' ? (
                        // Nothing to type, and a box would only invite it. A hidden
                        // zero keeps the field on the wire, where the server expects a
                        // number whichever type was chosen.
                        hidden('discount_value', '0')
                    ) : (
                        <Input
                            name={name('discount_value')}
                            inputMode="decimal"
                            autoComplete="off"
                            className="w-16 text-right tabular-nums"
                            aria-label={t('orders.line.discount_value')}
                            aria-invalid={!!error('discount_value')}
                            value={line.discountValue}
                            onChange={(event) =>
                                set({ discountValue: event.target.value })
                            }
                        />
                    )}
                </div>

                <InputError
                    role="alert"
                    message={error('discount_type') ?? error('discount_value')}
                />
            </div>

            <div className="flex items-center gap-2 sm:justify-center sm:pt-2.5">
                <Checkbox
                    id={taxableId}
                    checked={line.taxable}
                    // Radix can report `'indeterminate'`, which nothing here sets;
                    // comparing rather than coercing keeps it from reading as true.
                    onCheckedChange={(checked) =>
                        set({ taxable: checked === true })
                    }
                />
                <Label htmlFor={taxableId} className="font-normal sm:sr-only">
                    {t('orders.line.taxable')}
                </Label>
                {hidden('taxable', line.taxable ? '1' : '0')}
            </div>

            {/* `lineAmounts()` answers at the working scale — four places, what a line
                is stored at — and `MYR 120.0000` beside a total reading `MYR 120.00` is
                two answers to one question. One line taxed at nothing is that same net,
                rounded by the function that rounds the totals. A row still missing a
                quantity or a price comes to nothing, and says so. */}
            <p className="flex justify-between gap-2 text-sm sm:block sm:pt-2 sm:text-right">
                <span className="text-muted-foreground sm:sr-only">
                    {t('orders.line.amount')}
                </span>
                <span className="tabular-nums">
                    {formatMoney(orderTotals([line], '0').subtotal, currency)}
                </span>
            </p>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="justify-self-end sm:mt-0.5"
                aria-label={t('orders.line.remove', { number: index + 1 })}
                onClick={onRemove}
            >
                <Trash2 className="size-4" />
            </Button>
        </li>
    );
}
