import { z } from 'zod';
import { optionalText, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\LocationRequest and the `locations` columns —
 * `name` varchar(255), `code` varchar(50), `address` text capped at 1000 by the form.
 *
 * Whether the code is already taken is left to the server on purpose: only the
 * database can answer it, and the answer arrives through the same error bag as
 * everything here.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export const locationSchema = z.object({
    name: text({ attribute: 'validation.attributes.name', max: 255 }),
    code: optionalText({ attribute: 'validation.attributes.code', max: 50 }),
    address: optionalText({
        attribute: 'validation.attributes.address',
        max: 1000,
    }),
});
