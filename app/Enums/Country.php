<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The countries this app knows how to trade in.
 *
 * One list, because the same two codes drive three separate things: a customer's
 * address, the tenant's own tax treatment, and which e-invoice standard a document is
 * built for. A free-text country code would quietly produce an e-invoice no tax
 * authority accepts, which is why this is an enum and not a `string` column with a
 * comment.
 *
 * `#[TypeScript]` emits `App.Enums.Country` as `'MY' | 'SG'`, so the browser and the
 * server cannot drift on what the valid codes are — adding a country here widens the
 * TypeScript union on the next `bun run types:generate`.
 *
 * The NAMES are not here. A country's name is a user-facing string like any other and
 * lives in `lang/{locale}/countries.php`, keyed by these codes.
 */
#[TypeScript]
enum Country: string
{
    case MY = 'MY';
    case SG = 'SG';

    /**
     * Every code, for a validation rule or a picker.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
