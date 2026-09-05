<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Why a movement happened.
 *
 * The value is what lands in `stock_movements.reason`, so these strings are data and
 * cannot be renamed without a migration. The words a person reads are not here — they
 * are strings like any other and live in `lang/{locale}/stock.php`, keyed by these
 * codes. v1's enum carried an English `label()`, which is exactly the leak
 * `bun run check:i18n` exists to catch.
 *
 * Only {@see Adjustment} is reachable today. The rest are the vocabulary the later
 * phases write with — a purchase receipt, a sale, a production run — and they are
 * declared now because the ledger is append-only: a reason added later cannot be
 * applied to rows already written, so the set has to be right before there are any.
 *
 * `#[TypeScript]` emits `App.Enums.StockMovementReason`, so a reason the browser does
 * not know about is a tsc error rather than an unlabelled row.
 */
#[TypeScript]
enum StockMovementReason: string
{
    /** Somebody corrected the number by hand. The only one a person picks directly. */
    case Adjustment = 'adjustment';

    /** A counted total replaced the running one — see StockService::setLevel(). */
    case StockTake = 'stock_take';

    /** The two halves of a transfer. Always written as a pair, never alone. */
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    /** Phase 5: goods arriving from and going back to a supplier. */
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';

    /** Phase 5: goods leaving for and coming back from a customer. */
    case SalesFulfillment = 'sales_fulfillment';
    case SalesReturn = 'sales_return';

    /** Phase 5: materials consumed by a production run, and the product it made. */
    case ProductionConsume = 'production_consume';
    case ProductionOutput = 'production_output';

    /**
     * The reasons a person may choose on the movements screen.
     *
     * Everything else is written by the system as the side effect of something else —
     * a receipt, a sale, a transfer — and offering them here would let somebody record
     * a purchase receipt that no purchase order knows about.
     *
     * @return list<string>
     */
    public static function manual(): array
    {
        return [self::Adjustment->value];
    }
}
