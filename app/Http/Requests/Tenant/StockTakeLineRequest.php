<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\StockItem;

/**
 * Adding an item somebody found on a shelf the warehouse does not carry.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/stock-take-line.ts.
 *
 * One field, and it is the whole feature. v1's sheet was closed — it could only count
 * what `warehouse_stocks` already knew about — so a genuine surplus of something that
 * had never been booked into the building had nowhere to be written down, and the person
 * holding it either left it off the count or went and created a movement to make the
 * line appear. The sheet is open here, and that is the difference.
 *
 * **The count is not a field on this request.** A found item starts uncounted, like every
 * other line, and is counted through the same route as the rest — see
 * {@see StockTakeCountRequest}. Accepting a quantity here would be a second way to write
 * `counted_quantity`, differing from the first in whether null was allowed.
 *
 * The warehouse is the route, not a field, for the reason
 * {@see WarehouseReorderLevelRequest} gives: it is the sheet you are standing on, and
 * accepting it in the body would let a request write a line onto somebody else's count.
 */
final class StockTakeLineRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // One field, not a type and an id — see StockItem on why. The duplicate check
            // is not here: it is a question about this take's existing lines, which the
            // controller answers where it is already holding them.
            'item' => ['required', 'string', $this->itemExists()],
        ];
    }
}
