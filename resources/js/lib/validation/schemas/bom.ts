import { z } from 'zod';
import { decimal, id, lines } from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\BomRequest — a product's whole bill of materials,
 * replaced in one save.
 *
 * `quantity` is the first `decimal(15,4)` field in the app, and the scale is the part
 * that matters: this number is multiplied by every future production order, so a value
 * MySQL would silently round from 1.12345 to 1.1235 is a quantity that quietly stops
 * being the one somebody typed. {@see decimal} refuses it here and `decimal:0,4`
 * refuses it there.
 *
 * A factory, because the material ids are the server's — sent as a page prop — so the
 * browser cannot check a value against a list the workspace has since changed.
 *
 * The attribute keys carry the `items.*.` prefix because that is where Laravel looks
 * for them: it turns `items.0.quantity` back into `items.*.quantity` before reading
 * `validation.attributes`, so writing the same key here is what makes both layers call
 * the field by one name.
 *
 * `bun run check:validation` builds it with the arguments in that script's FACTORY_ARGS
 * and fails if this and the FormRequest stop covering the same fields.
 */
export const bomSchema = (rawMaterialIds: readonly number[]) =>
    z.object({
        items: lines({
            item: z.object({
                raw_material_id: id({
                    ids: rawMaterialIds,
                    attribute: 'validation.attributes.items.*.raw_material_id',
                }),
                quantity: decimal({
                    attribute: 'validation.attributes.items.*.quantity',
                }),
            }),
            max: 200,
            attribute: 'validation.attributes.items',
            distinct: {
                field: 'raw_material_id',
                attribute: 'validation.attributes.items.*.raw_material_id',
            },
        }),
    });
