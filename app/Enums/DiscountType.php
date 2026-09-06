<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How a discount on one order line is expressed.
 *
 * A percentage and a fixed amount are both ordinary ways to say the same thing, and which
 * one was meant has to be stored rather than inferred: 10 off a line of 100 and 10% off it
 * come to the same money today and to different money the moment the quantity changes.
 *
 * `None` is a case rather than a null so a line always answers the question. The column is
 * not nullable and no screen has to render "no discount" as an absence.
 */
#[TypeScript]
enum DiscountType: string
{
    case None = 'none';
    case Percent = 'percent';
    case Amount = 'amount';
}
