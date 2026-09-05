<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockOnHandData;
use App\Http\Requests\Tenant\StockOnHandRequest;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Decimals;
use App\Support\StockItem;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only lookups the stock dialogs make while somebody is choosing.
 *
 * Not an Inertia page — it returns JSON, which is what a Data object becomes when a
 * controller returns one. That is the reason it is a separate controller rather than a
 * method on {@see StockMovementController}: everything there renders or redirects, and
 * a JSON endpoint mixed in among them is a shape nobody expects to find.
 *
 * **Why a lookup rather than a page prop.** Every on-hand row could travel with the
 * page, and for a small workspace that would be a few dozen rows. But the number changes
 * whenever anybody else records anything, and a figure baked into the page at load goes
 * quietly stale while a dialog sits open. One request per choice is cheap and current.
 */
final class StockLookupController
{
    /**
     * What is on hand for one (warehouse, item) pair.
     *
     * Unlocked and therefore already out of date — see {@see StockService::onHand()}.
     * It exists so the person typing can see what they are working with; the refusal at
     * submit time is what actually protects the number.
     */
    public function onHand(StockOnHandRequest $request, StockService $stock): StockOnHandData
    {
        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));
        $item = StockItem::decode((string) $request->string('item'));

        // Unreachable — the request rule just checked it — but findOrFail's 404 is the
        // wrong shape for a lookup the browser is polling, and a null here would be a
        // TypeError rather than an answer.
        abort_if($item === null, Response::HTTP_UNPROCESSABLE_ENTITY);

        return new StockOnHandData(
            on_hand: Decimals::trim($stock->onHand($warehouse, $item)),
            unit: $item->unit,
        );
    }
}
