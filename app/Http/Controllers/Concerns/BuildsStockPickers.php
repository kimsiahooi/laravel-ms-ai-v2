<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Data\StockItemOptionData;
use App\Data\WarehouseOptionData;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Support\StockItem;

/**
 * The two pickers every stock screen needs: which warehouse, and which item.
 *
 * A trait rather than a Service because there is no logic here to get wrong — it is two
 * ordered lists shaped for a form. Movements need them today; transfers, stock takes and
 * reorder levels all need the same two.
 */
trait BuildsStockPickers
{
    /**
     * Warehouses, ordered by site and then by name.
     *
     * Site first because that is how somebody looks for one: you know where it is
     * before you know what it is called, and the screen groups the list under site
     * headings, which a name-first order would break into runs.
     *
     * @return list<WarehouseOptionData>
     */
    protected function warehouseOptions(): array
    {
        $warehouses = Warehouse::query()
            ->with('location')
            ->get()
            ->map(WarehouseOptionData::fromWarehouse(...))
            ->sortBy([['site', 'asc'], ['name', 'asc']]);

        return array_values($warehouses->all());
    }

    /**
     * Products and raw materials in one list, each valued `product:5` — see
     * {@see StockItem} on why the picker is one field.
     *
     * Products first, then materials, each alphabetical. Not interleaved: the screen
     * groups the list under two headings, and a merged order would split each heading's
     * rows into several runs.
     *
     * Whole lists rather than a search endpoint, the same trade the catalog pickers
     * make — a workspace has hundreds of items at most, and one query here is cheaper
     * than a round trip per keystroke there. Trashed rows are excluded by each model's
     * SoftDeletes scope, which is exactly what `StockItem::decode()` re-checks on the
     * way back in: an item nobody can pick is also an item nobody can submit.
     *
     * @return list<StockItemOptionData>
     */
    protected function itemOptions(): array
    {
        $products = Product::query()->orderBy('name')->get()
            ->map(StockItemOptionData::fromModel(...));

        $items = $products->concat(
            RawMaterial::query()->orderBy('name')->get()->map(StockItemOptionData::fromModel(...)),
        );

        return array_values($items->all());
    }
}
