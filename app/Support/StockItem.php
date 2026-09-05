<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StockItemType;
use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Model;

/**
 * The one-field encoding of "which product or raw material", and its inverse.
 *
 * A movement is recorded against either a product or a raw material, so the form needs
 * a single picker over both. Two fields — a type and an id — would mean two error keys
 * for one control, and whichever failed, the picker could only underline itself once.
 * So the picker's value is one string, `product:5`, and this is the only place that
 * knows the shape.
 *
 * **`decode()` is the validation, not a parser with a check bolted on.** It returns null
 * for anything it cannot turn into a live row: a wrong shape, an unknown type, an id
 * that does not exist, or a row that has been soft-deleted. That last one is the reason
 * it queries through the model rather than trusting the id — the same thing
 * {@see ActiveExists} does for an ordinary foreign key, which cannot be used here
 * because the table to look in is part of the value.
 */
final class StockItem
{
    /**
     * `product:5` for a model. The inverse of {@see decode()}.
     */
    public static function encode(Model $item): string
    {
        return $item->getMorphClass().':'.$item->getKey();
    }

    /**
     * The live row a picker value names, or null if there is not one.
     *
     * Null covers every way the value can be wrong, deliberately: a caller that has to
     * distinguish "malformed" from "deleted" would be building a message nobody can act
     * on differently.
     *
     * The return type names both models rather than saying `?Model`. There are exactly
     * two things a workspace holds stock of, and callers legitimately read `->unit` and
     * `->name` off the result — which a bare `Model` cannot promise, and which would
     * otherwise be waved through with a cast.
     */
    public static function decode(string $value): Product|RawMaterial|null
    {
        if (! str_contains($value, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $value, 2);

        if (! ctype_digit($id)) {
            return null;
        }

        return match (StockItemType::tryFrom($type)) {
            StockItemType::Product => Product::query()->find((int) $id),
            StockItemType::RawMaterial => RawMaterial::query()->find((int) $id),
            null => null,
        };
    }
}
