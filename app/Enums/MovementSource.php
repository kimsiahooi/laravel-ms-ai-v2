<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The kinds of document a ledger row can point back at.
 *
 * These are the morph-map keys stored in `stock_movements.source_type`, named here so the
 * browser gets a union rather than a bare string and so adding a case is a decision
 * somebody makes rather than a value that turns up. Phase 5 brings the rest — purchase
 * receipts, sales fulfilments, production orders — and each will add a case here, which
 * is a compile error on the screen until it says how it should be rendered.
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
    case StockTake = 'stock_take';
    case StockTransfer = 'stock_transfer';
}
