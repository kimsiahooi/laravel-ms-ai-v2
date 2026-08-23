import { z } from 'zod';
import { optionalEmail, optionalText, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\SupplierRequest and the `suppliers` columns —
 * `tax_id` varchar(100), `phone` varchar(50), `address` and `notes` text capped at
 * 1000 by the form.
 *
 * Whether the email is already on another supplier is left to the server: only the
 * database can answer it, and the answer arrives through the same error bag as
 * everything here.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export const supplierSchema = z.object({
    name: text({ attribute: 'validation.attributes.name', max: 255 }),
    contact_person: optionalText({
        attribute: 'validation.attributes.contact_person',
        max: 255,
    }),
    email: optionalEmail({
        attribute: 'validation.attributes.email',
        max: 255,
    }),
    tax_id: optionalText({
        attribute: 'validation.attributes.tax_id',
        max: 100,
    }),
    phone: optionalText({
        attribute: 'validation.attributes.phone',
        max: 50,
    }),
    address: optionalText({
        attribute: 'validation.attributes.address',
        max: 1000,
    }),
    notes: optionalText({
        attribute: 'validation.attributes.notes',
        max: 1000,
    }),
});
