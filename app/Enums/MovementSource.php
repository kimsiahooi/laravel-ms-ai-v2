<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The kinds of document a ledger row can point back at.
 *
 * These are the morph-map keys stored in `stock_movements.source_type`, named here so the
 * browser gets a union rather than a bare string and so adding a case is a decision
 * somebody makes rather than a value that turns up. Each new case is a compile error on the
 * screen that renders it until somebody says how it should be rendered — which is how
 * `purchase_return` arrived, and how the sales return still to come will.
 *
 * Not every source has a screen to open. A stock take does; a transfer does not, because
 * transfers are a list and have no detail page. The screen decides that, not this enum —
 * see the ledger's source cell.
 *
 * Read with `tryFrom`, never `from`: the column is written by the morph map and could in
 * principle hold a key this enum has not been taught, and a ledger row that cannot name
 * its source is still a ledger row.
 */
#[TypeScript]
enum MovementSource: string
{
    /** A receipt against a purchase order. Has a screen: the order itself. */
    case PurchaseOrder = 'purchase_order';

    /** A despatch against a sales order. Has a screen, for the same reason. */
    case SalesOrder = 'sales_order';

    /**
     * Goods sent back to a supplier, at the moment the return was completed.
     *
     * The source is the *return*, not the order it credits — the return is the document that
     * moved the stock, and it is the one somebody asking "where did those forty go?" needs to
     * open. The order is one hop from there.
     */
    case PurchaseReturn = 'purchase_return';

    case StockTake = 'stock_take';
    case StockTransfer = 'stock_transfer';
}
