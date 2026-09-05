<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Unit;
use App\Services\StockService;
use App\Support\Decimals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What is in one warehouse, for the dialog to show while somebody types.
 *
 * The unit travels with it because "18" and "18 kg" answer different questions, and the
 * form has no other way to know — the item picker's rows carry a name and a SKU, not a
 * unit. It is the enum value, not a word: the words live in `lang/units.php`.
 *
 * **This number is out of date the moment it is sent.** Nothing here locks anything —
 * see {@see StockService::onHand()}. It is a guide for the person typing,
 * and the refusal at submit time is the guarantee.
 */
#[TypeScript]
final class StockOnHandData extends Data
{
    public function __construct(
        /** Trimmed for reading — see {@see Decimals}. */
        public string $on_hand,
        public Unit $unit,
    ) {}
}
