import { z } from 'zod';
import { oneOf, optionalDecimal } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\WarehouseReorderLevelRequest.
 *
 * A function of what the screen listed, like the other stock schemas: the browser can
 * only refuse an item it was told does not exist. The warehouse is not here because it
 * is not a field — it is the route, so that the screen you are looking at is the only
 * one a submission can change.
 *
 * **`min_stock` is empty-able, and empty is not zero.** An empty box means no
 * threshold; zero means somebody set one to zero, which the server then reads as the
 * same instruction and deletes the row. Both are legal to type and the difference is
 * settled in one place, on the server.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function warehouseReorderLevelSchema(itemValues: readonly string[]) {
    return z.object({
        item: oneOf({
            values: itemValues,
            attribute: 'validation.attributes.item',
        }),
        min_stock: optionalDecimal({
            attribute: 'validation.attributes.min_stock',
            // gte:0, matching the server: zero is how a level is cleared from a
            // keyboard, so refusing it would be refusing an undo.
            gte: 0,
        }),
    });
}
