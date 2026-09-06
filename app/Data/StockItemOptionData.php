<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockItemType;
use App\Support\Decimals;
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
        /**
         * What the catalogue suggests this item is worth, or null if nobody has said.
         *
         * One field rather than a cost and a price, because a picker only ever asks from
         * one side of a trade: buying reads `raw_materials.default_cost`, selling will
         * read `products.default_price`, and no screen needs both at once. It is a
         * **suggestion** — the line stores what was actually agreed, so this moving
         * later moves no history.
         *
         * Null is a real answer, and different from zero: "we have never bought this"
         * is not "it is free".
         */
        public ?string $default_amount,
    ) {}

    /**
     * @param  Model&object{name: string, sku: string}  $item
     */
    public static function fromModel(Model $item): self
    {
        // Whichever of the two the model carries — a raw material has a cost, a
        // product has a price, and neither has the other. Read through getAttribute
        // because the pair differ by table and this factory serves both.
        $suggested = $item->getAttribute('default_cost') ?? $item->getAttribute('default_price');

        return new self(
            value: StockItem::encode($item),
            name: $item->name,
            sku: $item->sku,
            type: StockItemType::from($item->getMorphClass()),
            default_amount: is_numeric($suggested) ? Decimals::trim((string) $suggested) : null,
        );
    }
}
