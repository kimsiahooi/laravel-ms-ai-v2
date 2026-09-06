<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

/**
 * Setting — or clearing — one item's reorder level in one warehouse.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/warehouse-reorder-level.ts.
 *
 * Two fields, because that is the whole decision: which item, and at what level. The
 * warehouse is the route, not a field — it is the screen you are standing on, and
 * accepting it in the body would let a request set a level in a warehouse the person
 * is not looking at.
 *
 * **`min_stock` is nullable, and null is not an oversight — it is "no level".** An
 * empty box means nobody has an opinion about this item here, which is a state worth
 * being able to get back to. The controller stores neither null nor zero: it deletes
 * the row, because a threshold of zero warns about nothing and a row that warns about
 * nothing is a row that has to be excluded by every query that reads the table.
 */
final class WarehouseReorderLevelRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // One field, not a type and an id — see StockItem on why.
            'item' => ['required', 'string', $this->itemExists()],
            // `gte:0` rather than the default `gt:0`: zero is how a level is cleared
            // from a keyboard, and refusing it would leave the only way out of a
            // threshold being to clear the box, which not every browser makes obvious.
            'min_stock' => ['nullable', ...$this->decimalRules('gte:0')],
        ];
    }
}
