<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a purchase order is in its one-way life.
 *
 * There are exactly two transitions and both lead out of {@see Pending}: receive the
 * goods, or cancel the order. Nothing comes back. A receipt that was wrong is corrected
 * by a purchase return, which leaves the delivery and the return both on the record —
 * the same discipline the append-only ledger keeps, applied to the document that drives
 * it. Cancelling is not a delete: it says an order was placed and then called off, which
 * a supplier and an auditor both have reason to know.
 *
 * Because both terminal states are terminal, "may this be edited" is `=== Pending`
 * everywhere rather than a list to keep in step with this enum.
 *
 * There is no `label()` and no `badgeVariant()`. v1's enum had both — an English word
 * frozen into PHP, which is exactly the leak `bun run check:i18n` exists to catch, and a
 * shadcn variant name, which is a decision about how a screen looks taken in a file that
 * has never seen one. The words live in `lang/{locale}/purchase-orders.php` under
 * `status`, keyed by these values; the badge is the screen's business.
 *
 * `#[TypeScript]` emits `App.Enums.PurchaseOrderStatus`, so a status the browser does not
 * know about is a tsc error rather than a blank chip.
 */
#[TypeScript]
enum PurchaseOrderStatus: string
{
    /** Placed with the supplier. The only state in which the order may be changed. */
    case Pending = 'pending';

    /** The goods arrived and went into a warehouse. Terminal. */
    case Received = 'received';

    /** Called off without anything arriving. Terminal. */
    case Cancelled = 'cancelled';
}
