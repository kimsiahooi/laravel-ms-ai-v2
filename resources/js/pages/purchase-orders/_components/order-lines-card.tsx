import { useMemo } from 'react';
import type { OrderLine } from '@/components/form/order-lines-field';
import { OrderLinesField } from '@/components/form/order-lines-field';
import type { StockPickerEntry } from '@/components/form/stock-picker-field';
import InputError from '@/components/input-error';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type Item = App.Data.PurchaseOrderItemData;

/** One line as the request carries it. Every value a string, because a form sends strings. */
export type LinePayload = {
    item: string;
    quantity: string;
    unit_cost: string;
    discount_type: string;
    discount_value: string;
    taxable: string;
};

/**
 * What is being bought, at what price, and what it comes to.
 *
 * A thin wrapper around the shared {@see OrderLinesField}, and it exists for the seam
 * underneath it rather than for the card around it: the editor is shared with sales
 * orders, so the money on a line is a **unit price** when selling and a **unit cost**
 * when buying. The editor takes that name as a prop rather than fixing one and
 * translating the other: the input's `name`, the field the server refuses and the key
 * `runGate`'s `focusFirstInvalid` searches by all have to be the same string, and a
 * rename applied to only the error bag left the message rendering under a box that
 * focus could no longer find.
 */
export function OrderLinesCard({
    lines,
    onChange,
    materials,
    errors,
    currency,
    taxRate,
}: {
    lines: OrderLine[];
    onChange: (lines: OrderLine[]) => void;
    /**
     * What may be bought. Raw materials only — a purchase order buys what the workspace
     * consumes, and the request refuses a finished product outright — so the picker is
     * left ungrouped: one heading over every row would be furniture.
     */
    materials: App.Data.StockItemOptionData[];
    /** The whole bag, keyed the way Laravel and the zod gate both key it. */
    errors: Record<string, string>;
    currency: string;
    taxRate: string;
}) {
    const { t } = useTranslation();

    /**
     * What the catalogue suggests each material costs, by picker value.
     *
     * Built once from the same list the picker is drawn from, so a prefill can never
     * offer a price for something that was not on offer.
     */
    const suggested = useMemo(
        () =>
            new Map(
                materials
                    .filter((material) => material.default_amount !== null)
                    .map((material) => [
                        material.value,
                        material.default_amount ?? '',
                    ]),
            ),
        [materials],
    );

    /**
     * Fill the cost from the catalogue when a material is chosen — and only then.
     *
     * **Only into an empty box.** The default is where a line starts, not what it is: a
     * cost somebody has already typed is the price they actually agreed, and having it
     * overwritten by picking the same material again would be losing their work. Editing
     * an existing order never triggers it either, because those lines arrive with a cost.
     *
     * The line stores what was typed regardless, so this changes what is suggested and
     * never what is recorded.
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
            materials.map((material) => ({
                value: material.value,
                primary: material.name,
                secondary: material.sku,
            })),
        [materials],
    );

    return (
        <Card>
            <CardContent className="space-y-4">
                <h2 className="font-medium">
                    {t('purchase-orders.lines.heading')}
                </h2>

                <OrderLinesField
                    lines={lines}
                    onChange={handleChange}
                    entries={entries}
                    errors={errors}
                    currency={currency}
                    taxRate={taxRate}
                    itemLabel="orders.line.item"
                    // A purchase order records a cost, not a price. Naming the
                    // field rather than renaming its errors afterwards keeps the
                    // input's `name`, the server's field and the key that
                    // `focusFirstInvalid` looks the input up by all one string.
                    priceField="unit_cost"
                    priceLabel="orders.line.unit_cost"
                    pricePlaceholder="orders.line.unit_cost_placeholder"
                />

                {/* An order with no lines orders nothing. The message belongs under the
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
 * `key` is the index only at seeding time and never again: the editor hands out the next
 * key itself from there on, and it is identity rather than position — removing the second
 * of five rows must not renumber the three below it.
 *
 * The discount value is dropped along with a `none` type, matching what the editor does
 * when somebody switches back to it, so a stored `0.0000` does not reappear in a box that
 * is not shown.
 */
export function seedLines(items: Item[]): OrderLine[] {
    return items.map((item, index) => ({
        key: index,
        item: item.item,
        quantity: item.quantity,
        unitPrice: item.unit_cost,
        discountType: item.discount_type,
        discountValue: item.discount_type === 'none' ? '' : item.discount_value,
        taxable: item.taxable,
    }));
}

/**
 * The editor's lines as the request carries them.
 *
 * Where `unit_price` becomes `unit_cost`, and where a cleared discount box becomes the
 * zero the server expects — a number is wanted whichever type was chosen, and `''` would
 * be read as a missing field rather than as "no discount".
 *
 * A checkbox is `'1'` or `'0'` rather than a boolean because everything else here is a
 * string and Laravel's `boolean` rule accepts exactly those from a request.
 */
export function toPayloadLines(lines: OrderLine[]): LinePayload[] {
    return lines.map((line) => ({
        item: line.item,
        quantity: line.quantity.trim(),
        unit_cost: line.unitPrice.trim(),
        discount_type: line.discountType,
        discount_value: line.discountValue.trim() || '0',
        taxable: line.taxable ? '1' : '0',
    }));
}
