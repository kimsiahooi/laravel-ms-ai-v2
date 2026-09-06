<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Actions\OpenStockTake;

/**
 * Starting a count of one warehouse.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/stock-take.ts.
 *
 * Two fields, because that is the whole decision: which warehouse, and why. Everything
 * else about a new take is not something anybody types — {@see OpenStockTake} prints the
 * sheet from what the warehouse currently holds, and the counts arrive one at a time
 * afterwards through {@see StockTakeCountRequest}.
 *
 * **What is deliberately not a rule here: whether the warehouse already has an open
 * count.** Two people counting different aisles of the same building at the same time is
 * a real way to work, and refusing the second sheet would be this file inventing a policy
 * the rest of the module does not hold — posting reconciles against live on-hand under
 * the lock, so a second sheet cannot corrupt the first one's result.
 */
final class StockTakeRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', ...$this->foreignKey('warehouses')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
