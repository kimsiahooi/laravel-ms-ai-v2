import { z } from 'zod';
import { id, optionalDecimal } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\StockTakeCountRequest.
 *
 * One row of the count sheet, saved on its own the moment it is entered rather than
 * with the rest at the end — which is why there is a schema for a single line at all.
 *
 * **`line` is checked against the ids this sheet was handed**, which is the browser
 * half of the server's rule scoping the id to this take's own lines. The authoritative
 * answer stays there, because only the server knows the sheet has not changed under
 * the reader; this catches the ordinary case, a page left open while the take was
 * posted or a line was added somewhere else.
 *
 * It goes through {@see id} rather than an integer check because that is the primitive
 * this codebase has for "one of the rows you were offered", and it reports
 * `validation.exists` — "that line is not on this sheet" — which is the true failure.
 * A line id that is not one of these is not a typo, it is a request against somebody
 * else's take. Ids arrive as strings, the way a form submits them.
 *
 * **`counted_quantity` accepts both empty and zero, and they are different answers.**
 * Empty is "not counted yet" and clears a count back to that; zero is somebody
 * standing in front of an empty shelf, which is one of the things a stock take exists
 * to record. Hence `gte: 0` rather than the primitive's default `gt: 0` — the same
 * line the server draws with `gte:0`, and the same one `stockMovementSchema` draws for
 * a movement that sets a level.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function stockTakeCountSchema(lineIds: readonly number[]) {
    return z.object({
        line: id({
            ids: lineIds,
            attribute: 'validation.attributes.line',
        }),
        counted_quantity: optionalDecimal({
            attribute: 'validation.attributes.counted_quantity',
            gte: 0,
        }),
    });
}
