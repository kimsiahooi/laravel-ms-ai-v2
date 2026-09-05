<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Product;
use App\Models\RawMaterial;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The two things a workspace can hold stock of.
 *
 * The values are the morph-map keys — see AppServiceProvider — so this enum and the
 * `stockable_type` column say the same words, and a movement's type can be read off the
 * row without a lookup.
 *
 * The words a person reads live in `lang/{locale}/stock-movements.php` under
 * `item_type`, keyed by these values. `#[TypeScript]` emits `App.Enums.StockItemType`,
 * so a type the browser does not know about is a tsc error rather than a blank badge.
 */
#[TypeScript]
enum StockItemType: string
{
    case Product = 'product';
    case RawMaterial = 'raw_material';

    /** The model class this type addresses. */
    public function model(): string
    {
        return match ($this) {
            self::Product => Product::class,
            self::RawMaterial => RawMaterial::class,
        };
    }
}
