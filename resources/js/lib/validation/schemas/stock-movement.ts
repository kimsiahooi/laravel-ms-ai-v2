import { z } from 'zod';
import { decimal, id, oneOf, optionalText } from '@/lib/validation/primitives';

/** What the form can say happened. Mirrors StockMovementRequest::TYPES. */
export const MOVEMENT_TYPES = ['in', 'out', 'set'] as const;

export type MovementType = (typeof MOVEMENT_TYPES)[number];

/**
 * Mirrors App\Http\Requests\Tenant\StockMovementRequest.
 *
 * A function of what the pickers offered, like `productSchema` and `warehouseSchema`:
 * the browser can only refuse an item or a warehouse it was told does not exist.
 *
 * **The quantity bound depends on the type**, which is why the type is an argument
 * rather than something the schema reads at parse time. Moving zero in or out would
 * append a row to an append-only ledger saying nothing happened; setting the level *to*
 * zero is a real correction — "the shelf is empty" — so zero is legal only there. The
 * server draws the same line, in `StockMovementRequest::rules()`.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function stockMovementSchema(
    itemValues: readonly string[],
    warehouseIds: readonly number[],
    type: MovementType,
) {
    return z.object({
        warehouse_id: id({
            ids: warehouseIds,
            attribute: 'validation.attributes.warehouse_id',
        }),
        item: oneOf({
            values: itemValues,
            attribute: 'validation.attributes.item',
        }),
        type: oneOf({
            values: MOVEMENT_TYPES,
            attribute: 'validation.attributes.type',
        }),
        quantity: decimal({
            attribute: 'validation.attributes.quantity',
            // gte:0 for a level, gt:0 for a move — the same split as the server.
            ...(type === 'set' ? { gte: 0 } : { gt: 0 }),
        }),
        notes: optionalText({
            attribute: 'validation.attributes.notes',
            max: 1000,
        }),
    });
}
