<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Tenant\PurchaseReturnController;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Removes a return that has not happened.
 *
 * **An Action rather than three lines in the controller, and completion is what made it one.**
 * While every return was pending, {@see PurchaseReturnController::destroy()}'s status check could
 * not lose: there was no other state to race against. Now there is, and the check it was making
 * — read the status off the model the route had already bound, outside any transaction, then
 * delete — is the exact shape {@see ReceivePurchaseOrder} was written to avoid:
 *
 * 1. Somebody presses Complete. {@see CompletePurchaseReturn} takes the return's row.
 * 2. Somebody else presses Delete on a tab that still says pending, passes the check, and blocks
 *    on the `UPDATE`.
 * 3. The completion commits: goods off the shelf, ledger rows written.
 * 4. The delete proceeds — against a return that has just completed.
 *
 * The damage is worse than an odd-looking document. Ledger rows would name something nobody can
 * open, and because {@see ReturnedQuantities} lets a trashed return release its quantity, the
 * delivery would offer back stock that has physically left the building. Re-reading the status
 * under the same lock the completion takes is what closes it.
 *
 * Soft, like every delete here. The lines go with it by the relation's cascade only when the row
 * is force-deleted; a soft delete leaves {@see PurchaseReturnItem} rows in place, which is
 * correct — they fall out of the ceiling through the parent's global scope rather than by being
 * removed.
 */
final class DeletePurchaseReturn
{
    /**
     * @throws DomainException when the return stopped being pending between the controller's
     *                         check and this lock.
     */
    public function handle(PurchaseReturn $return): void
    {
        DB::transaction(function () use ($return): void {
            $locked = PurchaseReturn::query()->whereKey($return->getKey())->lockForUpdate()->first();

            // Already gone — two people pressed at once, or a stale tab. The outcome the caller
            // wanted is the outcome, so there is nothing to say.
            if ($locked === null) {
                return;
            }

            if ($locked->status !== ReturnStatus::Pending) {
                throw new DomainException('Purchase return is no longer pending.');
            }

            $locked->delete();
        });
    }
}
