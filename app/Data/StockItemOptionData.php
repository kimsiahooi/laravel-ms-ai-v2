<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockItemType;
use App\Support\StockItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry in the merged product / raw-material picker.
 *
 * Not {@see OptionData}, and the difference is the id: an ordinary picker's value is a
 * number, while this one addresses two tables at once and so carries the encoded string
 * from {@see StockItem}. The type travels separately from the value even
 * though the value contains it, because the browser renders it as a badge and should not
 * be splitting strings to find out what to draw.
 *
 * The sku travels because two products can share a name and nothing stops them; the sku
 * is the thing that tells a picker's two "Oak board" rows apart.
 */
#[TypeScript]
final class StockItemOptionData extends Data
{
    public function __construct(
        /** The encoded picker value, e.g. `product:5`. */
        public string $value,
        public string $name,
        public string $sku,
        public StockItemType $type,
    ) {}

    /**
     * @param  Model&object{name: string, sku: string}  $item
     */
    public static function fromModel(Model $item): self
    {
        return new self(
            value: $item->getMorphClass().':'.$item->getKey(),
            name: $item->name,
            sku: $item->sku,
            type: StockItemType::from($item->getMorphClass()),
        );
    }
}
