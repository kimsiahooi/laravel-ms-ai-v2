import { z } from 'zod';
import { optionalText, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\CategoryRequest and the `categories` columns —
 * `name` varchar(255), `description` text capped at 1000 by the form.
 *
 * Whether the name is already taken is left to the server on purpose: only the
 * database can answer it, and the answer arrives through the same error bag as
 * everything here.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export const categorySchema = z.object({
    name: text({ attribute: 'validation.attributes.name', max: 255 }),
    description: optionalText({
        attribute: 'validation.attributes.description',
        max: 1000,
    }),
});
