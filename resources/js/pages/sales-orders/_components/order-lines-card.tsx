import { useMemo } from 'react';
import type { OrderLine } from '@/components/form/order-lines-field';
import { OrderLinesField } from '@/components/form/order-lines-field';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import InputError from '@/components/input-error';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type Item = App.Data.SalesOrderItemData;

/** One line as the request carries it. Every value a string, because a form sends strings. */
export type LinePayload = {
    item: string;
    quantity: string;
    unit_price: string;
    discount_type: string;
    discount_value: string;
    taxable: string;
};

/**
 * What is being sold, at what price, and what it comes to.
 *
 * A thin wrapper around the shared {@see OrderLinesField} — and a thinner one than the
 * purchase side's, which is the point worth noticing. That editor was written in the
 * selling direction: its money column is called `unit_price` by default, and its labels
 * default to `orders.line.unit_price`. So this card passes **no** price-naming props at
 * all, where `purchase-orders/_components/order-lines-card.tsx` has to pass three to rename
 * the column into a cost.
 *
 * The counterpart to {@see seedLines} and {@see toPayloadLines} below, which are likewise
 * pass-throughs here and renames there.
 */
export function OrderLinesCard({
    lines,
    onChange,
    products,
    errors,
    currency,
    taxRate,
}: {
    lines: OrderLine[];
    onChange: (lines: OrderLine[]) => void;
    /**
     * What may be sold. Finished products only — a sales order sells what the workspace
     * makes, and the request refuses a raw material outright — so the picker is left
     * ungrouped: one heading over every row would be furniture.
     */
    products: App.Data.StockItemOptionData[];
    /** The whole bag, keyed the way Laravel and the zod gate both key it. */
    errors: Record<string, string>;
    currency: string;
    taxRate: string;
}) {
    const { t } = useTranslation();

    /**
     * What the catalogue suggests each product sells for, by picker value.
     *
     * Built once from the same list the picker is drawn from, so a prefill can never offer
     * a price for something that was not on offer. `StockItemOptionData::fromModel()` reads
     * `default_cost ?? default_price`, and a product carries only the second — so this is
     * the selling price with no special casing.
     */
    const suggested = useMemo(
        () =>
            new Map(
                products
                    .filter((product) => product.default_amount !== null)
                    .map((product) => [
                        product.value,
                        product.default_amount ?? '',
                    ]),
            ),
        [products],
    );

    /**
     * Fill the price from the catalogue when a product is chosen — and only then.
     *
     * **Only into an empty box.** The default is where a line starts, not what it is: a
     * price somebody has already typed is what they actually quoted, and having it
     * overwritten by picking the same product again would be losing their work. Editing an
     * existing order never triggers it either, because those lines arrive with a price.
     *
     * The line stores what was typed regardless, so this changes what is suggested and never
     * what is recorded.
     */
    const handleChange = (next: OrderLine[]) => {
        onChange(
            next.map((line, index) => {
                const previous = lines[index];
                const picked =
                    previous !== undefined &&
                    previous.item !== line.item &&
                    line.item !== '';

                if (!picked || line.unitPrice.trim() !== '') {
                    return line;
                }

                const fill = suggested.get(line.item);

                return fill === undefined ? line : { ...line, unitPrice: fill };
            }),
        );
    };

    const entries = useMemo<StockPickerEntry[]>(
        () =>
            products.map((product) => ({
                value: product.value,
                primary: product.name,
                secondary: product.sku,
            })),
        [products],
    );

    return (
        <Card>
            <CardContent className="space-y-4">
                <h2 className="font-medium">
                    {t('sales-orders.lines.heading')}
                </h2>

                {/* No priceField, priceLabel or pricePlaceholder: the editor already
                    defaults to `unit_price` and its selling labels. See the class note. */}
                <OrderLinesField
                    lines={lines}
                    onChange={handleChange}
                    entries={entries}
                    errors={errors}
                    currency={currency}
                    taxRate={taxRate}
                    itemLabel="orders.line.item"
                />

                {/* An order with no lines sells nothing. The message belongs under the
                    editor rather than beside a field, because it is about the list as a
                    whole rather than about any one row. */}
                <InputError role="alert" message={errors.items} />
            </CardContent>
        </Card>
    );
}

/**
 * The stored lines as the editor holds them.
 *
 * `key` is the index only at seeding time and never again: the editor hands out the next key
 * itself from there on, and it is identity rather than position — removing the second of five
 * rows must not renumber the three below it.
 *
 * The discount value is dropped along with a `none` type, matching what the editor does when
 * somebody switches back to it, so a stored `0.0000` does not reappear in a box that is not
 * shown.
 */
export function seedLines(items: Item[]): OrderLine[] {
    return items.map((item, index) => ({
        key: index,
        item: item.item,
        quantity: item.quantity,
        unitPrice: item.unit_price,
        discountType: item.discount_type,
        discountValue: item.discount_type === 'none' ? '' : item.discount_value,
        taxable: item.taxable,
    }));
}

/**
 * The editor's lines as the request carries them.
 *
 * A straight pass-through on the money column, where the purchase side renames `unit_price`
 * to `unit_cost`. What is left is the two conversions both sides need: a cleared discount box
 * becomes the zero the server expects — a number is wanted whichever type was chosen, and
 * `''` would be read as a missing field rather than as "no discount" — and a checkbox becomes
 * `'1'` or `'0'` rather than a boolean, because everything else here is a string and
 * Laravel's `boolean` rule accepts exactly those from a request.
 */
export function toPayloadLines(lines: OrderLine[]): LinePayload[] {
    return lines.map((line) => ({
        item: line.item,
        quantity: line.quantity.trim(),
        unit_price: line.unitPrice.trim(),
        discount_type: line.discountType,
        discount_value: line.discountValue.trim() || '0',
        taxable: line.taxable ? '1' : '0',
    }));
}
