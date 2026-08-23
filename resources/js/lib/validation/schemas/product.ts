import { z } from 'zod';
import {
    oneOf,
    optionalId,
    optionalText,
    text,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\ProductRequest and the `products` columns —
 * `sku` and `barcode` varchar(100), `description` capped at 2000 by the form.
 *
 * A factory, because three of its fields check against lists the server owns: the unit
 * codes, and the ids behind the two pickers. All three arrive as page props so the
 * browser cannot validate against a list the server has since changed. The caller
 * memoises it on them.
 *
 * `bun run check:validation` builds it with the arguments in that script's FACTORY_ARGS
 * and fails if this and the FormRequest stop covering the same fields.
 */
export const productSchema = (
    units: readonly string[],
    categoryIds: readonly number[],
    supplierIds: readonly number[],
) =>
    z.object({
        name: text({ attribute: 'validation.attributes.name', max: 255 }),
        sku: text({ attribute: 'validation.attributes.sku', max: 100 }),
        barcode: optionalText({
            attribute: 'validation.attributes.barcode',
            max: 100,
        }),
        description: optionalText({
            attribute: 'validation.attributes.description',
            max: 2000,
        }),
        category_id: optionalId({
            ids: categoryIds,
            attribute: 'validation.attributes.category_id',
        }),
        supplier_id: optionalId({
            ids: supplierIds,
            attribute: 'validation.attributes.supplier_id',
        }),
        unit: oneOf({
            values: units,
            attribute: 'validation.attributes.unit',
        }),
    });
