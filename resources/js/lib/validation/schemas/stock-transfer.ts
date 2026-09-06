import { z } from 'zod';
import {
    decimal,
    different,
    id,
    oneOf,
    optionalText,
} from '@/lib/validation/primitives';

/**
 * Mirrors App\Http\Requests\Tenant\StockTransferRequest.
 *
 * A function of what the pickers offered, like `stockMovementSchema`: the browser can
 * only refuse a warehouse or an item it was told does not exist.
 *
 * **What is deliberately absent: whether there is enough stock.** The dialog shows what
 * is on hand at the source, but that number was read before the lock and would be acted
 * on after it, so checking it here would be a guess dressed as a rule. StockService
 * refuses under the lock, and the answer arrives on the quantity field.
 *
 * `bun run check:validation` fails if this and the FormRequest stop covering the same
 * fields.
 */
export function stockTransferSchema(
    itemValues: readonly string[],
    warehouseIds: readonly number[],
) {
    return (
        z
            .object({
                from_warehouse_id: id({
                    ids: warehouseIds,
                    attribute: 'validation.attributes.from_warehouse_id',
                }),
                to_warehouse_id: id({
                    ids: warehouseIds,
                    attribute: 'validation.attributes.to_warehouse_id',
                }),
                item: oneOf({
                    values: itemValues,
                    attribute: 'validation.attributes.item',
                }),
                // Always a magnitude: unlike the ledger there is no direction in the sign,
                // and a transfer of nothing is a document that says nothing.
                quantity: decimal({
                    attribute: 'validation.attributes.quantity',
                    gt: 0,
                }),
                notes: optionalText({
                    attribute: 'validation.attributes.notes',
                    max: 1000,
                }),
            })
            // Stock that leaves a warehouse and arrives at the same one has not moved, and
            // the pair of ledger rows it would write cancel out to a record of nothing.
            .superRefine(
                different({
                    field: 'to_warehouse_id',
                    other: 'from_warehouse_id',
                    attribute: 'validation.attributes.to_warehouse_id',
                    otherAttribute: 'validation.attributes.from_warehouse_id',
                }),
            )
    );
}
