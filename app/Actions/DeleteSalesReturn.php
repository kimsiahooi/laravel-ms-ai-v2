<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Tenant\SalesReturnController;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Removes a credit note that has not happened.
 *
 * **An Action rather than three lines in the controller**, for the reason
 * {@see DeletePurchaseReturn} sets out at length: reading the status off the model the route
 * bound, outside any transaction, and then deleting is a race that
 * {@see CompleteSalesReturn} can win. The loser's `UPDATE` then soft-deletes a return that has
 * just completed — leaving ledger rows naming a document nobody can open, and, because
 * {@see ReturnedQuantities} lets a trashed return release its quantity, handing the despatch back
 * room for goods that are already on the shelf.
 *
 * Re-reading the status under the same lock the completion takes is what closes it.
 *
 * Soft, like every delete here. {@see SalesReturnItem} rows stay in place and fall out of the
 * ceiling through the parent's global scope rather than by being removed — which is the whole
 * mechanism, and why {@see SalesReturnController::destroy()} can be honest about what deleting
 * does.
 */
final class DeleteSalesReturn
{
    /**
     * @throws DomainException when the return stopped being pending between the controller's
     *                         check and this lock.
     */
    public function handle(SalesReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $locked = SalesReturn::query()->whereKey($return->getKey())->lockForUpdate()->first();

            // Already gone — two people pressed at once, or a stale tab. The outcome the caller
            // wanted is the outcome, so there is nothing to say.
            if ($locked === null) {
                return;
            }

            if ($locked->status !== ReturnStatus::Pending) {
                throw new DomainException('Sales return is no longer pending.');
            }

            $locked->delete();
        });
    }
}
