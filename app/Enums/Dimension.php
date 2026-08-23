<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a unit measures. Two quantities can only be converted between units of the same
 * dimension — grams to kilograms is arithmetic, kilograms to litres is a bug that would
 * show up as somebody's stock figure.
 *
 * {@see Unit} carries one of these per case, and it is also how the picker groups
 * itself: fourteen units in a flat list is a wall, the same fourteen under Mass, Volume,
 * Length and Count is a menu.
 *
 * `Count` is the odd one and deliberately so. A box, a roll and a sheet are all "one of
 * something", but there is no universal number of pieces in a box — a box of screws and
 * a box of paper hold different amounts, and that number is a property of the material,
 * not of the word. So count units carry a factor of 1 and never convert into each other;
 * see {@see Unit::isConvertibleTo()}.
 */
#[TypeScript]
enum Dimension: string
{
    case Mass = 'mass';
    case Volume = 'volume';
    case Length = 'length';
    case Count = 'count';
}
