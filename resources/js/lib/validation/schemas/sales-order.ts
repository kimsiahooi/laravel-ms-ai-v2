import { z } from 'zod';
import { encodeMessage } from '@/lib/validation/message';
import {
    decimal,
    lines,
    oneOf,
    optionalDateTime,
    optionalDecimal,
    optionalFlag,
    optionalText,
    text,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\SalesOrderRequest — the order header and every line it
 * is taken with, in one save.
 *
 * A factory over the currencies alone, because that is the only list the browser can hold
 * an opinion about: `BusinessSetting::allowedCurrencies()` is sent as a page prop, so a
 * workspace that drops a currency cannot leave the browser accepting one the request
 * would refuse.
 *
 * **What is deliberately absent, part one: whether the customer and the products still
 * exist.** `ActiveExists` asks a question about the database at the moment of the request,
 * and this schema is not handed the ids to guess with. What it checks is the half a browser
 * can answer honestly — that something was chosen at all — and `required` is exactly the
 * sentence the server would produce for the failure it can see.
 *
 * **Part two, and it matters more here than on the purchase side: whether there is any
 * stock.** Nothing in this schema looks at availability, and nothing should. A sales order
 * is a commitment to sell, routinely taken before the goods exist; the order carries no
 * warehouse until it is fulfilled, so there is no level to check a line against. Refusing a
 * line here would make a backorder impossible to write down. Availability is a fact about
 * one warehouse at one instant, and only the fulfilment action can establish it, under a
 * lock.
 *
 * **No total is checked, because no total is sent.** The lines are the whole of what the
 * server is told; `App\Support\OrderTotals` works the rest out again. A schema that
 * validated a subtotal would be validating a number nobody may submit.
 *
 * **Duplicate lines are allowed**, unlike a bill of materials. Two lines for the same
 * product at two prices is how a price break is written down, and `distinct` would refuse
 * the ordinary case to prevent one nobody makes.
 *
 * `bun run check:validation` builds it with the arguments in that script's FACTORY_ARGS and
 * fails if this and the FormRequest stop covering the same fields.
 */
export function salesOrderSchema(currencies: readonly string[]) {
    return (
        z
            .object({
                customer_id: text({
                    attribute: 'validation.attributes.customer_id',
                }),
                currency: oneOf({
                    values: currencies,
                    attribute: 'validation.attributes.currency',
                }),
                // `decimal(15,6)`, not the working scale of every other number here: a rate
                // is a ratio rather than an amount, and four places turns 0.212345 into a
                // figure that is out by a ringgit on every ten thousand. Nine integer digits
                // are what is left of the fifteen, which is the ceiling below.
                //
                // Required, like the rule it mirrors — and it always arrives, because an
                // order taken in the base currency has no rate box and the form sends the 1
                // itself. `SalesOrderRequest::prepareForValidation()` overwrites it to 1 on
                // that side for the same reason, so a blank rate on a FOREIGN-currency order
                // is refused by both layers rather than quietly assumed to be 1.
                exchange_rate: decimal({
                    attribute: 'validation.attributes.exchange_rate',
                    scale: EXCHANGE_RATE_SCALE,
                    max: EXCHANGE_RATE_MAX,
                    gt: 0,
                }),
                expected_date: optionalDateTime(
                    'validation.attributes.expected_date',
                ),
                notes: optionalText({
                    attribute: 'validation.attributes.notes',
                    max: 1000,
                }),
                items: lines({
                    item: z.object({
                        // `product:5` — a StockItem value, not a bare id, so an {@see id}
                        // over numbers is the wrong shape even if the list were here. That
                        // the value must name a *live product* is a question about the
                        // catalogue, and the request asks it there.
                        item: text({
                            attribute: 'validation.attributes.items.*.item',
                        }),
                        quantity: decimal({
                            attribute: 'validation.attributes.items.*.quantity',
                        }),
                        // Zero is legal where the quantity's zero is not: a sample or a
                        // replacement shipped at no charge is a line worth recording, and
                        // selling none of something is not.
                        unit_price: decimal({
                            attribute:
                                'validation.attributes.items.*.unit_price',
                            gte: 0,
                        }),
                        discount_type: oneOf({
                            values: DISCOUNT_TYPES,
                            attribute:
                                'validation.attributes.items.*.discount_type',
                        }),
                        // Empty-able, exactly as the rule is: a cleared discount box is not
                        // a missing answer, it is no discount, and both layers read it as
                        // zero. The form sends `'0'` rather than `''`, so this is the looser
                        // of the two only for a payload the form did not build.
                        discount_value: optionalDecimal({
                            attribute:
                                'validation.attributes.items.*.discount_value',
                            gte: 0,
                        }),
                        // `optionalFlag`, where the server says `required|boolean`. The
                        // checkbox posts `'1'` or `'0'` on every row, so the required half
                        // is unreachable from this form — and a browser stricter than the
                        // server is the failure worth avoiding, not one a notch looser.
                        taxable: optionalFlag({
                            attribute: 'validation.attributes.items.*.taxable',
                        }),
                    }),
                    max: MAX_LINES,
                    attribute: 'validation.attributes.items',
                }),
            })
            // An order with no lines sells nothing. {@see lines} has no floor of its own — a
            // bill of materials may legitimately be emptied — so the rule that makes this a
            // document rather than a note is stated here, filed against `items` so it lands
            // on the editor rather than at the top of the page.
            .superRefine((value, ctx) => {
                if ((value.items ?? []).length > 0) {
                    return;
                }

                ctx.addIssue({
                    code: 'custom',
                    path: ['items'],
                    message: encodeMessage({
                        key: 'validation.required',
                        attribute: 'validation.attributes.items',
                    }),
                });
            })
    );
}

/** How a line's discount is expressed. Mirrors App\Enums\DiscountType. */
const DISCOUNT_TYPES = ['none', 'percent', 'amount'] as const;

/**
 * What `sales_orders.exchange_rate` holds — `decimal(15,6)`, so nine integer digits and six
 * after the point. The same two numbers the FormRequest caps at, and for the same reason: an
 * over-large rate should be refused rather than overflow the column.
 */
const EXCHANGE_RATE_SCALE = 6;
const EXCHANGE_RATE_MAX = 999_999_999;

/** `max:N` on the lines. One order, not a data import. */
const MAX_LINES = 200;
