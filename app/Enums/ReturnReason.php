<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Why goods are going back — one answer per return, beside the free-text notes.
 *
 * **One reason per document, not per line.** A return is usually one thing that went wrong with
 * one delivery, so the reason belongs to the document; a line-level answer would put a select in
 * a grid that already carries a quantity and a ceiling per row, to record a distinction most
 * returns do not have. A return that genuinely mixes two reasons is two returns, which is also
 * the more honest record.
 *
 * **Five cases, and they are deliberately about the goods rather than about the trade.** That is
 * what lets one enum serve both directions: a supplier sends something damaged and a customer
 * sends something back damaged, and it is the same fact. Wording that took a side — "customer
 * changed their mind", "over-delivery" — would be wrong in one of the two modules, the way
 * `permissions.php` is careful not to be.
 *
 * The notes field is still there and still matters: this says which shelf the return goes on,
 * and the notes say what actually happened.
 *
 * There is no `label()`, for the reason {@see ReturnStatus} gives. The words live in
 * `lang/{locale}/returns.php` under `reason`, and the browser composes `returns.reason.{value}`.
 */
#[TypeScript]
enum ReturnReason: string
{
    /** Arrived broken, crushed or spoiled. About the condition, not the choice. */
    case Damaged = 'damaged';

    /** Not what the order said — a different item, or the wrong variant of it. */
    case WrongItem = 'wrong_item';

    /** The right item, in one piece, and not up to what was specified. */
    case Quality = 'quality';

    /** More than was needed, or no longer needed. Nothing is wrong with the goods. */
    case Surplus = 'surplus';

    /** Everything else. The notes carry it — see the class note. */
    case Other = 'other';
}
