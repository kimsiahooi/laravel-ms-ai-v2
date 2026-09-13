<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\ReturnedQuantities;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a return is in its one-way life — the same three states both kinds of return have.
 *
 * There are exactly two transitions and both lead out of {@see Pending}: complete the return,
 * which moves the stock, or cancel it, which moves nothing. Nothing comes back. A completed
 * return that was wrong is corrected by the document that reverses it, never by reopening this
 * one — the append-only discipline the ledger keeps, applied to the paperwork that drives it.
 *
 * **Shared by purchase and sales returns**, unlike the two order statuses, which are separate
 * enums because "received" and "fulfilled" are genuinely different words for genuinely
 * different events. A return completes in both directions and the word is the same one.
 *
 * There is no `label()` and no `badgeVariant()`. v1's `ReturnStatus` had both — an English word
 * frozen into PHP, which is exactly the leak `bun run check:i18n` exists to catch, and a shadcn
 * variant name, which is a decision about how a screen looks taken in a file that has never seen
 * one. The words live in `lang/{locale}/returns.php` under `status`, keyed by these values.
 *
 * `#[TypeScript]` emits `App.Enums.ReturnStatus`, so a status the browser does not know about is
 * a tsc error rather than a blank chip.
 */
#[TypeScript]
enum ReturnStatus: string
{
    /** Raised and not yet acted on. The only state in which a return may be changed. */
    case Pending = 'pending';

    /** The goods went back and the stock moved. Terminal. */
    case Completed = 'completed';

    /** Called off without anything moving. Terminal. */
    case Cancelled = 'cancelled';

    /**
     * The statuses that consume an order line's returnable quantity.
     *
     * **A pending return counts, and that is the point of this method existing.** It is a claim
     * on goods somebody intends to send back, so a second return claiming the same goods is not
     * a sensible document to raise — and the earliest honest moment to say so is while somebody
     * is still typing. Cancelled does not count: nothing left the building and the goods are
     * still returnable, which makes cancelling the release valve.
     *
     * **Here rather than in {@see ReturnedQuantities}** so the completion path in the Action
     * cannot forget it, and so cancelled is excluded by the type rather than by a `!==` somebody
     * re-derives in a second file. Note that the Action deliberately asks a *narrower* question
     * under its lock — only what has actually moved — and says so where it does.
     *
     * @return list<self>
     */
    public static function consuming(): array
    {
        return [self::Pending, self::Completed];
    }
}
