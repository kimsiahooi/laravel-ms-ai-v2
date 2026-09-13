<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\CompletePurchaseReturn;
use App\Http\Requests\Tenant\PurchaseReturnRequest;
use App\Support\ReturnedQuantities;
use RuntimeException;

/**
 * Thrown when a return can no longer be completed because a sibling got there first.
 *
 * **Not the same refusal as a short shelf**, and the difference is what the reader should do
 * about it. {@see InsufficientStockForOrderException} says the goods are not in *this* warehouse,
 * and the answer may be to pick another one. This says the delivery has no room left for the
 * quantity on this document however much stock is lying around — the answer is to edit the
 * return down, or to drop it.
 *
 * **Reachable only through a race, and a narrow one.** {@see PurchaseReturnRequest} already
 * refuses an over-return at save time counting pending siblings, so a document that passes there
 * fits. Two returns raised concurrently can each fit on their own and not together — that is the
 * race {@see SavePurchaseReturn} declines to serialise, on the grounds that nothing has moved and
 * an editor can correct it. This is where the bill for that decision arrives: the first
 * completion succeeds, the second is refused here, and the second return is still pending and
 * still editable.
 *
 * **Every line that no longer fits, not the first** — the argument
 * {@see InsufficientStockForOrderException} makes for itself. "This would take 7 where 4 remain"
 * is unreadable on a twelve-line document without the name beside it.
 *
 * Raised only by {@see CompletePurchaseReturn}, from numbers
 * {@see ReturnedQuantities::completedForOrderItems()} built under the parent order's lock — so
 * unlike the ceiling on the form, these are the numbers as of the instant nothing else could
 * change them.
 */
final class ReturnExceedsOrderException extends RuntimeException
{
    /**
     * @param  non-empty-list<array{name: string|null, remaining: string, requested: string}>  $lines
     *                                                                                                 one row per line that no longer fits, in the order they sit on the return. `name`
     *                                                                                                 is null where the material has since been force-deleted, which is the same nothing
     *                                                                                                 every other screen in this module shows for it.
     */
    public function __construct(public readonly array $lines)
    {
        parent::__construct('Purchase return exceeds what the delivery has left.');
    }
}
