import { z } from 'zod';
import { compareDecimal } from '@/lib/money';
import { encodeMessage } from '@/lib/validation/message';
import {
    decimal,
    id,
    lines,
    oneOf,
    optionalText,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\SalesReturnRequest.
 *
 * The mirror of `purchaseReturnSchema`, and every note on it holds. A factory over two things
 * the page already holds — the order it credits, and what each of that order's lines may still
 * take back — so the browser can never check against a list the server did not send.
 *
 * **The ceiling is checked per row, and it is the reason this schema exists at all.** Everything
 * else here the server would catch a round trip later; "only 3 of this line are left" is the one
 * message worth having before the request leaves, because it is about a number the person is
 * looking at while they type.
 *
 * **No money is validated, because none is sent.** A credit note's prices are copied from the
 * order line it names — see the FormRequest. The only number on this form is a quantity.
 */
export const salesReturnSchema = (
    orderId: number,
    /** `sales_order_item_id` → what may still come back, as a trimmed decimal string. */
    remaining: Readonly<Record<string, string>>,
) => {
    const orderItemIds = Object.keys(remaining).map(Number);

    return (
        z
            .object({
                sales_order_id: id({
                    ids: [orderId],
                    attribute: 'validation.attributes.sales_order_id',
                }),
                reason: oneOf({
                    values: REASONS,
                    attribute: 'validation.attributes.reason',
                }),
                notes: optionalText({
                    attribute: 'validation.attributes.notes',
                    max: 1000,
                }),
                items: lines({
                    item: z
                        .object({
                            sales_order_item_id: id({
                                ids: orderItemIds,
                                attribute:
                                    'validation.attributes.items.*.sales_order_item_id',
                            }),
                            quantity: decimal({
                                attribute:
                                    'validation.attributes.items.*.quantity',
                            }),
                        })
                        // On the row rather than on the field: zod cannot see a sibling from
                        // inside `quantity`, and the ceiling is a fact about the pair.
                        .superRefine((row, ctx) => {
                            const limit = remaining[row.sales_order_item_id];

                            // A line the page was not told about. Its own `id` check has
                            // already refused it; inventing a ceiling would be a second
                            // message about one mistake.
                            if (limit === undefined) {
                                return;
                            }

                            // Null while the box holds `1.` — "must be a number" is another
                            // rule's message. Scaled BigInt, never `Number()`: decimal(15,4)
                            // at its maximum is fifteen significant digits, the edge of a
                            // double, and a float comparison would disagree with the server's
                            // `bccomp` silently on exactly the quantities somebody checks by
                            // hand.
                            const over = compareDecimal(row.quantity, limit);

                            if (over === null || over <= 0) {
                                return;
                            }

                            ctx.addIssue({
                                code: 'custom',
                                // Relative to the row, so the gate composes
                                // `items.2.quantity` — the key Laravel files it under and the
                                // key `focusFirstInvalid` resolves to the input's name.
                                path: ['quantity'],
                                message: encodeMessage({
                                    key: 'sales-returns.validation.over_return',
                                    // No `attribute`, matching the server's `errors()->add()`,
                                    // which does no `:attribute` replacement. Both layers
                                    // produce one identical sentence.
                                    params: { remaining: limit },
                                }),
                            });
                        }),
                    max: MAX_LINES,
                    attribute: 'validation.attributes.items',
                    // The table's unique index makes a repeated line a 500 rather than a
                    // message, so the browser refuses it here and the FormRequest's `distinct`
                    // refuses it again.
                    distinct: {
                        field: 'sales_order_item_id',
                        attribute:
                            'validation.attributes.items.*.sales_order_item_id',
                    },
                }),
            })
            // A return with no lines returns nothing. {@see lines} has no floor of its own, so
            // the rule that makes this a document is stated here and filed against `items`, so
            // it lands on the grid rather than at the top of the page.
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
};

/**
 * Mirrors App\Enums\ReturnReason — the same enum the purchase side reads, because "damaged"
 * means the same thing whichever direction the goods travel.
 *
 * `satisfies` catches a case renamed or removed on the server; the form's own
 * `Record<App.Enums.ReturnReason, TranslationKey>` catches one added. Together they are
 * exhaustive — neither alone is.
 */
const REASONS = [
    'damaged',
    'wrong_item',
    'quality',
    'surplus',
    'other',
] as const satisfies readonly App.Enums.ReturnReason[];

/** `max:N` on the lines. One despatch, not a data import — the order's own cap. */
const MAX_LINES = 200;
