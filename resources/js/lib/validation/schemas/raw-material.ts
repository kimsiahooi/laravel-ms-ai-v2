import { z } from 'zod';
import { oneOf, optionalText, text } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\RawMaterialRequest and the `raw_materials` columns
 * — `sku` and `barcode` varchar(100), `unit` varchar(20).
 *
 * Three required fields, which is what makes this module different from the parties in
 * the catalog: a material with no code and no unit is a row nothing downstream can use.
 *
 * A factory rather than a constant, and only because of `unit`: the valid codes are
 * App\Enums\Unit's and they arrive as a page prop, so the browser cannot check against
 * a list the server has since changed. The caller memoises it on the codes.
 *
 * Whether the SKU is already taken is left to the server — only the database can answer
 * it, and the answer arrives through the same error bag as everything here.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export const rawMaterialSchema = (units: readonly string[]) =>
    z.object({
        name: text({ attribute: 'validation.attributes.name', max: 255 }),
        sku: text({ attribute: 'validation.attributes.sku', max: 100 }),
        barcode: optionalText({
            attribute: 'validation.attributes.barcode',
            max: 100,
        }),
        // Not free text with a length cap — "kg" and "KG" would be two units to a
        // stock engine that later adds their quantities together.
        unit: oneOf({
            values: units,
            attribute: 'validation.attributes.unit',
        }),
    });
