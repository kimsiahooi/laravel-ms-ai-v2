<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\FulfillSalesOrder;
use App\Data\StockAvailabilityData;
use App\Support\OrderAvailability;
use RuntimeException;

/**
 * Thrown when a document cannot be issued because one or more of its products is short.
 *
 * The sibling of {@see InsufficientStockException}, and the difference is the one that
 * matters on a document: that one carries an `available` and a `requested` but **no item**,
 * which is fine for a receipt where the case is unreachable and useless on a twelve-line
 * order — "you have 4, this would take 7" leaves somebody reading twelve rows to find which
 * four. This one carries the rows themselves.
 *
 * **Every short product, not the first.** Stopping at the first would turn one despatch into
 * as many round trips as there are shortfalls, each one revealing the next. The check is run
 * over the whole order before anything is written, so the whole answer is already in hand.
 *
 * A refusal rather than a fault, like its sibling: somebody asked to ship more than is on the
 * shelf. Callers turn it into a message on the screen it was pressed from, and nothing reports
 * it.
 *
 * Raised only by {@see FulfillSalesOrder}, from rows {@see OrderAvailability} built — so the
 * numbers in it are the same numbers the panel on the page was showing, computed the same way.
 */
final class InsufficientStockForOrderException extends RuntimeException
{
    /**
     * @param  non-empty-list<StockAvailabilityData>  $shortfalls  one row per product that
     *                                                             cannot be covered, in the
     *                                                             order the lines are on the
     *                                                             document
     */
    public function __construct(public readonly array $shortfalls)
    {
        parent::__construct('Sales order cannot be fulfilled from this warehouse.');
    }
}
