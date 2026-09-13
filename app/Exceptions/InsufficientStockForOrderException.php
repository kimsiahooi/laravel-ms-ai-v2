<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\CompletePurchaseReturn;
use App\Actions\FulfillSalesOrder;
use App\Data\StockAvailabilityData;
use App\Support\OrderAvailability;
use RuntimeException;

/**
 * Thrown when a document cannot be issued because one or more of its items is short.
 *
 * The sibling of {@see InsufficientStockException}, and the difference is the one that
 * matters on a document: that one carries an `available` and a `requested` but **no item**,
 * which is fine for a receipt where the case is unreachable and useless on a twelve-line
 * order — "you have 4, this would take 7" leaves somebody reading twelve rows to find which
 * four. This one carries the rows themselves.
 *
 * **Every short item, not the first.** Stopping at the first would turn one despatch into
 * as many round trips as there are shortfalls, each one revealing the next. The check is run
 * over the whole document before anything is written, so the whole answer is already in hand.
 *
 * A refusal rather than a fault, like its sibling: somebody asked to take more off the shelf
 * than is on it. Callers turn it into a message on the screen it was pressed from, and nothing
 * reports it.
 *
 * **One class for both documents that take stock off a shelf** — {@see FulfillSalesOrder} and
 * {@see CompletePurchaseReturn} — rather than a second copy with the same shape. The payload is
 * already document-neutral: a sales order issues products and a purchase return issues raw
 * materials, and {@see StockAvailabilityData} serves both, which is the whole reason
 * {@see OrderAvailability} was generalised. The numbers in it are the same numbers the panel on
 * the page was showing, computed the same way.
 */
final class InsufficientStockForOrderException extends RuntimeException
{
    /**
     * @param  non-empty-list<StockAvailabilityData>  $shortfalls  one row per item that cannot
     *                                                             be covered, in the order the
     *                                                             lines are on the document
     */
    public function __construct(public readonly array $shortfalls)
    {
        // Deliberately says nothing about which kind of document: no user ever reads it —
        // both callers build their own translated sentence from `$shortfalls` — and a message
        // naming sales orders on a purchase return's stack trace is worse than a vague one.
        parent::__construct('Document cannot be issued from this warehouse.');
    }
}
