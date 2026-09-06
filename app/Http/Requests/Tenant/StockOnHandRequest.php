<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

/**
 * The on-hand lookup's query string: `?warehouse_id=7&item=product:5`.
 *
 * The same two fields {@see StockMovementRequest} validates, and deliberately the same
 * rules — a pair the movement form would refuse must not be a pair this will answer for.
 *
 * No zod counterpart, and `check:validation` is told so: this is a lookup the dialog
 * makes on its own while somebody is choosing, not a form anyone fills in and submits.
 * There is no field for an error to land under.
 */
final class StockOnHandRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', ...$this->foreignKey('warehouses')],
            'item' => ['required', 'string', $this->itemExists()],
        ];
    }
}
