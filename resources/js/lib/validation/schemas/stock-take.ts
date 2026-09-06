import { z } from 'zod';
import { id, optionalText } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\StockTakeRequest.
 *
 * A function of the warehouses the page offered, like every other stock schema: the
 * browser can only refuse a warehouse it was told does not exist. Whether that row is
 * still there when the request lands is a fact about the database, and `ActiveExists`
 * is the side that can answer it.
 *
 * **Two fields, and the count sheet is not one of them.** Opening a take snapshots
 * every stock row the warehouse holds, which is work the server does under a
 * transaction and nothing a form can describe — so this checks the single decision
 * somebody makes here, and the sheet is validated a row at a time afterwards by
 * {@see stockTakeCountSchema}.
 *
 * There is no edit form to mirror either. A take is opened once and from then on
 * changed only by counting it, posting it or cancelling it.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function stockTakeSchema(warehouseIds: readonly number[]) {
    return z.object({
        warehouse_id: id({
            ids: warehouseIds,
            attribute: 'validation.attributes.warehouse_id',
        }),
        notes: optionalText({
            attribute: 'validation.attributes.notes',
            max: 1000,
        }),
    });
}
