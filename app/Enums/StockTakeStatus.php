<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a count sheet is in its one-way life.
 *
 * There are exactly two transitions and both lead out of {@see Draft}: post it, or
 * cancel it. Nothing comes back. A posted count is corrected by counting again, which
 * leaves the mistake and the correction both on the record — the same discipline the
 * append-only ledger keeps, applied to the document that drives it. Cancelling is not a
 * delete: it says somebody started a count and abandoned it, which is worth knowing.
 *
 * Because both terminal states are terminal, "may this be edited" is `=== Draft`
 * everywhere rather than a list to keep in step with this enum.
 *
 * There is no `label()`. The words a person reads live in
 * `lang/{locale}/stock-takes.php` under `status`, keyed by these values — a DTO ships
 * values and never sentences, and an English label here is exactly the leak
 * `bun run check:i18n` exists to catch.
 *
 * `#[TypeScript]` emits `App.Enums.StockTakeStatus`, so a status the browser does not
 * know about is a tsc error rather than a blank badge.
 */
#[TypeScript]
enum StockTakeStatus: string
{
    /** Being counted. The only state in which anything on the sheet may be written. */
    case Draft = 'draft';

    /** The counts were applied to on-hand. Terminal. */
    case Posted = 'posted';

    /** Abandoned without touching stock. Terminal. */
    case Cancelled = 'cancelled';
}
