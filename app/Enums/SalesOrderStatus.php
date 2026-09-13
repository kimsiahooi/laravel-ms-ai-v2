<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a sales order is in its one-way life.
 *
 * The mirror of {@see PurchaseOrderStatus}: exactly two transitions, both leading out of
 * {@see Pending}, and nothing comes back. Ship the goods, or call the order off. A shipment
 * that was wrong is corrected by a sales return, which leaves both the despatch and the
 * return on the record — the same discipline the append-only ledger keeps, applied to the
 * document that drives it. Cancelling is not a delete: it says an order was taken and then
 * called off, which a customer and an auditor both have reason to know.
 *
 * Because both terminal states are terminal, "may this be edited" is `=== Pending`
 * everywhere rather than a list to keep in step with this enum.
 *
 * No `label()` and no `badgeVariant()`, for the reasons {@see PurchaseOrderStatus} gives:
 * an English word frozen into PHP is the leak `bun run check:i18n` exists to catch, and a
 * shadcn variant name is a decision about how a screen looks taken in a file that has never
 * seen one. The words live in `lang/{locale}/sales-orders.php` under `status`, keyed by
 * these values; the badge is the screen's business.
 *
 * `#[TypeScript]` emits `App.Enums.SalesOrderStatus`, so a status the browser does not know
 * about is a tsc error rather than a blank chip.
 */
#[TypeScript]
enum SalesOrderStatus: string
{
    /** Taken from the customer. The only state in which the order may be changed. */
    case Pending = 'pending';

    /** The goods left a warehouse and went to the customer. Terminal. */
    case Fulfilled = 'fulfilled';

    /** Called off without anything shipping. Terminal. */
    case Cancelled = 'cancelled';
}
