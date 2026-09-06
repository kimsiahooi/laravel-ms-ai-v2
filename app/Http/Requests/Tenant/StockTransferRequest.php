<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Services\StockService;
use App\Support\StockItem;

/**
 * Moving stock from one warehouse to another.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/stock-transfer.ts.
 *
 * There is no update or delete request to pair with this, and that is the design: a
 * transfer is a record of something that happened. Sent the wrong way, it is corrected
 * by transferring back, which leaves both on the record.
 *
 * **What is deliberately not a rule here: whether there is enough stock.** Any answer
 * this could give would be read before the lock is taken and acted on after, so it
 * would be a guess dressed as a check. {@see StockService} refuses under
 * the lock and the controller turns that refusal into a message on the quantity.
 */
final class StockTransferRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', ...$this->foreignKey('warehouses')],
            // `different` before the existence check, so picking one warehouse twice is
            // answered by the sentence about that rather than by a lookup that passes.
            'to_warehouse_id' => [
                'required',
                'different:from_warehouse_id',
                ...$this->foreignKey('warehouses'),
            ],
            // One field, not a type and an id — see StockItem on why.
            'item' => ['required', 'string', $this->itemExists()],
            // A magnitude, always positive: unlike the ledger there is no direction to
            // encode in the sign, and moving nothing is a document that says nothing.
            'quantity' => ['required', ...$this->decimalRules()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
