<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Requests\Tenant\WarehouseReorderLevelRequest;
use App\Models\Warehouse;
use App\Models\WarehouseReorderLevel;
use App\Support\StockItem;
use App\Support\TenantPermissions;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one thing the warehouse screen can change: when to reorder.
 *
 * Its own controller rather than a sixth method on {@see WarehouseController}, because
 * it writes a different table for a different reason. That controller manages the
 * building; this one records a decision about what is kept in it, and the two have
 * separate permissions in everything but name — see `warehouses.reorder-levels.update`
 * in {@see TenantPermissions}.
 *
 * **No toast.** Every other write in the app announces itself, and this one deliberately
 * does not: the control is an input in a row, and somebody setting up twenty items would
 * get twenty notifications for edits whose result is already on screen in the cell they
 * just left. The row itself is the receipt — the number comes back from the server, and
 * the reorder badge appears or disappears with it.
 */
final class WarehouseReorderLevelController
{
    /**
     * Set the level, or clear it.
     *
     * Null and zero are the same instruction — "stop warning me about this" — and both
     * delete the row. See the migration on why zero is never stored.
     */
    public function update(WarehouseReorderLevelRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $item = StockItem::decode((string) $request->string('item'));

        // Unreachable: the request's `itemExists` rule just resolved the same value.
        // It is here because `decode()` returns null on failure and PHPStan is right to
        // insist that a null cannot be asked for its morph class.
        abort_if($item === null, Response::HTTP_UNPROCESSABLE_ENTITY);

        $key = [
            'warehouse_id' => $warehouse->id,
            'stockable_type' => $item->getMorphClass(),
            'stockable_id' => $item->getKey(),
        ];

        $level = (string) $request->string('min_stock');

        // Anything that is not a positive number clears the level. Null, an empty box
        // and zero are all the same instruction — "stop warning me about this" — and
        // the validator has already refused everything that is not one of those or a
        // decimal, so `is_numeric` here is what turns a checked string into one bccomp
        // will take rather than a second check.
        if (! is_numeric($level) || bccomp($level, '0', 4) <= 0) {
            WarehouseReorderLevel::query()->where($key)->delete();

            return back();
        }

        WarehouseReorderLevel::query()->updateOrCreate($key, ['min_stock' => $level]);

        return back();
    }
}
