import { z } from 'zod';
import { oneOf } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\StockTakeLineRequest.
 *
 * One field, because adding a found item asks one question: which item. Everything
 * else about the new line — what the system believed it held, which take it joins —
 * the server works out for itself, and a form that submitted any of it would be
 * offering to be lied to.
 *
 * {@see oneOf} rather than {@see id}: an item here addresses two tables at once and so
 * travels as `product:5` rather than as a number — see `App\Support\StockItem`. The
 * values are the picker's own and arrive at call time, like every other stock schema,
 * so a page left open while a product was archived is checked against what it was
 * actually offered rather than against a stale copy baked in at module scope.
 *
 * **What is deliberately absent: whether the item is already on the sheet.** The page
 * knows the answer as of its last render and the server knows it as of the request,
 * and between the two somebody else may have added the same tin of paint. The
 * duplicate is refused on this same field by the controller, where the answer is true
 * — checking it here as well would only teach the reader to distrust whichever of the
 * two spoke first.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function stockTakeLineSchema(itemValues: readonly string[]) {
    return z.object({
        item: oneOf({
            values: itemValues,
            attribute: 'validation.attributes.item',
        }),
    });
}
