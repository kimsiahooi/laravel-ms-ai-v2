import { z } from 'zod';
import { id, optionalText, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\WarehouseRequest and the `warehouses` columns.
 *
 * A function of the site ids, not a constant — the same shape as `productSchema`, and
 * for the same reason: the browser can only refuse an unknown site if it is told which
 * ones exist. It is the picker's own list, so a site the form never offered is refused
 * before the request is built, and `ActiveExists` refuses it again on arrival.
 *
 * `id()` rather than `optionalId()`: the column is NOT NULL, and a warehouse with no
 * site is not addressable.
 *
 * Whether the code is already taken is left to the server. Only the database can
 * answer it, and the answer arrives through the same error bag as everything here.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function warehouseSchema(locationIds: readonly number[]) {
    return z.object({
        location_id: id({
            ids: locationIds,
            attribute: 'validation.attributes.location_id',
        }),
        name: text({ attribute: 'validation.attributes.name', max: 255 }),
        code: optionalText({
            attribute: 'validation.attributes.code',
            max: 50,
        }),
        address: optionalText({
            attribute: 'validation.attributes.address',
            max: 1000,
        }),
    });
}
