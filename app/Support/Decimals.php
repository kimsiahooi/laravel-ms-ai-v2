<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turning a stored decimal into one a person reads.
 *
 * `decimal(15,4)` always comes back at full scale, so `40.5` is stored and returned as
 * `40.5000`. That is right for arithmetic and wrong for a screen: a ledger column of
 * `+40.5000` and `-3.0000` is four digits of noise per row, and the trailing zeros say
 * nothing except how the column was declared.
 *
 * Promoted here on its second consumer rather than its third, per ARCHITECTURE.md's
 * rule of three: the `.` guard below is the kind of non-trivial detail that gets copied
 * wrong the second time somebody needs it.
 */
final class Decimals
{
    /**
     * `2.5000` → `2.5`, `10.0000` → `10`, `0.1000` → `0.1`, `-3.0000` → `-3`.
     *
     * **The `.` guard is load-bearing**: trimming zeros off `10` without it gives `1`.
     */
    public static function trim(string $quantity): string
    {
        return str_contains($quantity, '.')
            ? rtrim(rtrim($quantity, '0'), '.')
            : $quantity;
    }
}
