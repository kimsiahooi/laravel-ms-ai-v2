<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WarehouseItemData;
use App\Enums\StockItemType;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * What one warehouse holds, and what it is supposed to hold.
 *
 * The read-side counterpart to {@see StockService}. That class is the only thing that
 * *writes* stock; this one is the only thing that asks the question a warehouse screen
 * asks — one row per catalogue item, with this warehouse's on-hand and this warehouse's
 * reorder level beside each other. Neither number means much alone.
 *
 * **Why this is a service and not four lines in the controller.** The question spans
 * four tables and cannot be asked with Eloquent: a workspace's catalogue lives in two
 * tables, `products` and `raw_materials`, with no parent to select from. One paginated,
 * sortable, searchable list over both is a `UNION ALL` — and `check-structure.sh` is
 * right that a controller is not where that belongs.
 *
 * **The join predicates live in the `ON` clause, not in a `WHERE`.** Moving
 * `warehouse_id = ?` out to a `WHERE` would turn both LEFT JOINs into inner ones and
 * silently drop every item this warehouse has never stocked — which is exactly the set
 * somebody comes here to set a level for.
 */
final class WarehouseInventory
{
    /**
     * Columns this list may be ordered by, named by their alias in the union.
     *
     * **This list is the SQL-injection guard**, for the reason
     * {@see SortsResourceQuery} spells out: `orderBy()`
     * interpolates the identifier rather than binding it. It lives here rather than in
     * the controller so the guard sits in the same file as the interpolation.
     *
     * @var list<string>
     */
    public const SORTABLE = ['name', 'sku', 'on_hand', 'min_stock'];

    /**
     * The narrowings the screen offers, beyond its default.
     *
     * The default is the absence of one — see {@see applyScope()} — so it has no name
     * here. A value outside this list is treated as no narrowing at all, the same
     * treatment an unknown `?sort=` gets.
     *
     * @var list<string>
     */
    public const SCOPES = ['attention', 'all'];

    /**
     * The default: alphabetical.
     *
     * Not "least stock first", which sounds more useful than it is — it answers one
     * question ("what is running out") that the `attention` scope answers better, and
     * makes the other one ("where is Oak board") impossible. A list you can predict the
     * position of a row in is a list you can come back to.
     */
    private const DEFAULT_SORT = 'name';

    /**
     * A page of this warehouse's items, plus the filter state to echo back.
     *
     * The return shape deliberately matches {@see RendersResourceIndex},
     * so the controller assembles `filters` the same way every other list does even
     * though it cannot use the trait — that takes an Eloquent builder and there is no
     * model here to give it.
     *
     * @param  string  $show  '' for what this warehouse holds, `attention` for what is
     *                        at or below its level, `all` for the whole catalogue
     * @return array{rows: LengthAwarePaginator<int, mixed>, sort: string, direction: 'asc'|'desc', sortable: list<string>}
     */
    public function page(
        Warehouse $warehouse,
        string $search,
        string $show,
        ?string $sort,
        ?string $direction,
        int $perPage,
    ): array {
        $sorted = $sort !== null && in_array($sort, self::SORTABLE, true);
        $column = $sorted ? $sort : self::DEFAULT_SORT;
        // Only a recognised column gets to choose its direction — the same rule, for the
        // same reason, as SortsResourceQuery: the header's arrow keys off `sort`, so a
        // lone `?direction=desc` would reorder a column the UI cannot show it on.
        $order = $sorted && $direction === 'desc' ? 'desc' : 'asc';

        $query = DB::query()->fromSub($this->union($warehouse->id), 'items');

        $this->applyScope($query, $show);

        if ($search !== '') {
            // Grouped, so the OR cannot escape and widen the scope applied above.
            $query->where(function (Builder $group) use ($search): void {
                $like = '%'.$search.'%';
                $group->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy($column, $order)
            // (type, id) is unique across both legs, so this is a total order. Without
            // it two items with the same name can swap between pages and one of them
            // never appears.
            ->orderBy('type', $order)
            ->orderBy('id', $order)
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString()
            ->through(WarehouseItemData::fromRow(...));

        return [
            'rows' => $rows,
            'sort' => $column,
            'direction' => $order,
            'sortable' => self::SORTABLE,
        ];
    }

    /**
     * The two numbers above the list, over the whole warehouse rather than the page.
     *
     * From the same union the list reads, so the summary and the rows cannot disagree —
     * a second query shaped by hand is a second definition of "needs reorder".
     *
     * @return array{in_stock: int, needs_reorder: int}
     */
    public function counts(Warehouse $warehouse): array
    {
        $row = DB::query()->fromSub($this->union($warehouse->id), 'items')
            ->selectRaw('SUM(CASE WHEN on_hand > 0 THEN 1 ELSE 0 END) as in_stock, SUM(needs_reorder) as needs_reorder')
            ->first();

        return [
            'in_stock' => (int) ($row->in_stock ?? 0),
            'needs_reorder' => (int) ($row->needs_reorder ?? 0),
        ];
    }

    /**
     * Which rows the screen is asking about.
     *
     * The default is not "everything". A catalogue of five hundred items in a warehouse
     * that holds forty of them is four hundred and sixty rows of zero, and the forty
     * that matter are somewhere inside it. So the unfiltered list is what this warehouse
     * *has to do with* — stock on the shelf, or a level somebody set — and asking for
     * the rest is a deliberate widening, because setting a level for something never
     * stocked here is a real thing to want.
     *
     * v1 wrote that default as `on_hand > 0 OR (min_stock > 0 AND on_hand < min_stock)`.
     * The second half is unreachable: if `min_stock` is set and `on_hand` is not below
     * it, then `on_hand >= min_stock > 0`, so the first half already matched. Same rows,
     * fewer words — and now `min_stock IS NOT NULL` says it directly, because a row in
     * `warehouse_reorder_levels` only exists when a level was actually set.
     */
    private function applyScope(Builder $query, string $show): void
    {
        match ($show) {
            'all' => null,
            'attention' => $query->where('needs_reorder', 1),
            default => $query->where(function (Builder $group): void {
                $group->where('on_hand', '>', 0)->orWhereNotNull('min_stock');
            }),
        };
    }

    /**
     * One row per live catalogue item, with this warehouse's numbers left-joined in.
     *
     * Two morph tables, so two legs — and exactly two, which is why {@see leg()} takes
     * the enum rather than a table name: adding a third thing a workspace can hold
     * stock of becomes a match that no longer compiles, instead of a leg somebody
     * forgot to add here.
     */
    private function union(int $warehouseId): Builder
    {
        return $this->leg(StockItemType::Product, $warehouseId)
            ->unionAll($this->leg(StockItemType::RawMaterial, $warehouseId));
    }

    /**
     * One leg: a catalogue table, joined to this warehouse's stock row and reorder-level
     * row where each exists.
     *
     * `on_hand` is coalesced to zero — an item with no stock row has none of it. Its
     * neighbour `min_stock` is deliberately **not** coalesced: null there means nobody
     * has an opinion, and a zero would claim somebody decided the threshold was zero.
     *
     * **`needs_reorder` is computed here, in SQL, and this is the only place it is
     * defined.** The row's badge, the `attention` scope and the summary count are all
     * the same question, and asking it three times in two languages is how the badge on
     * a row ends up disagreeing with the number above the list. MySQL also compares
     * DECIMALs exactly, which PHP would need `bccomp` and a pair of numeric-string
     * assertions to promise.
     *
     * The table name and the morph key are matched from the enum rather than passed as
     * strings, so every fragment interpolated below is a literal this file wrote.
     */
    private function leg(StockItemType $type, int $warehouseId): Builder
    {
        [$table, $key] = match ($type) {
            StockItemType::Product => ['products', 'product'],
            StockItemType::RawMaterial => ['raw_materials', 'raw_material'],
        };

        $alert = 'CASE WHEN rl.min_stock IS NOT NULL AND COALESCE(ws.quantity, 0) <= rl.min_stock'
            .' THEN 1 ELSE 0 END as needs_reorder';

        return DB::table($table)
            ->addSelect([
                "{$table}.id as id",
                "{$table}.name as name",
                "{$table}.sku as sku",
                "{$table}.unit as unit",
                'rl.min_stock as min_stock',
            ])
            ->selectRaw($key === 'product' ? "'product' as type" : "'raw_material' as type")
            ->selectRaw('COALESCE(ws.quantity, 0) as on_hand')
            ->selectRaw($alert)
            ->whereNull("{$table}.deleted_at")
            ->leftJoin('warehouse_stocks as ws', function ($join) use ($table, $key, $warehouseId): void {
                $join->on('ws.stockable_id', '=', "{$table}.id")
                    ->where('ws.stockable_type', $key)
                    ->where('ws.warehouse_id', $warehouseId);
            })
            ->leftJoin('warehouse_reorder_levels as rl', function ($join) use ($table, $key, $warehouseId): void {
                $join->on('rl.stockable_id', '=', "{$table}.id")
                    ->where('rl.stockable_type', $key)
                    ->where('rl.warehouse_id', $warehouseId);
            });
    }
}
