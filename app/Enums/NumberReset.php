<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether a document's count restarts each financial year.
 *
 * `Yearly` gives `PO-2026-0001` and starts again at one when the year turns; `Never` gives
 * `PO-0001` and counts on forever. Both are ordinary choices — the first is what most
 * accountants expect, the second is what somebody migrating from a system that never reset
 * needs so their numbers stay continuous.
 *
 * The choice lands in the sequence key rather than only in the format: see
 * `document_sequences.period`, which holds the year label or an empty string.
 */
#[TypeScript]
enum NumberReset: string
{
    case Yearly = 'yearly';
    case Never = 'never';
}
