<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\WarehouseData;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\WarehouseRequest;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Warehouses: list, create, edit, delete. Same shape as every other list — a dialog
 * over it, every write returning `back()`.
 *
 * No detail screen. v1 has one, and it is a stock report: a UNION over products and
 * raw materials, left-joined to on-hand quantities and reorder levels. Every table it
 * reads arrives with StockService, so the screen arrives then too rather than as an
 * empty frame now.
 *
 * Deleting is a soft delete, and unguarded — for the moment. What makes a warehouse
 * undeletable is stock still sitting in it, and there is nowhere to put stock yet.
 * That guard lands with `warehouse_stocks`, on the same reasoning that kept sites
 * unguarded until this module.
 */
final class WarehouseController
{
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by. This list is the SQL-injection guard for
     * `?sort=` — see {@see SortsResourceQuery}.
     *
     * `location` is absent: it is a name on another table, so ordering by it needs a
     * join, and joins are not a controller's job.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'code', 'created_at'];

    public function index(Request $request): Response
    {
        // The site filter. Ids are cast and then checked against the sites that
        // actually have a warehouse, so a hand-typed `?site=99999` or `?site=abc` is
        // no filter rather than an empty list — the same treatment, for the same
        // reason, as the products page's material filter.
        $sites = self::sitesWithWarehouses();
        $known = $sites->pluck('id')->all();

        $selected = collect(explode(',', $this->queryValue($request, 'site')))
            ->map(static fn (string $id): int => (int) trim($id))
            ->filter(static fn (int $id): bool => in_array($id, $known, true))
            ->unique()
            ->values();

        // `location` is eager-loaded because every row prints its site's name, and
        // `creator` because every row prints who added it.
        $query = Warehouse::query()->with(['location', 'creator']);

        if ($selected->isNotEmpty()) {
            $query->whereIn('location_id', $selected);
        }

        ['rows' => $warehouses, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: WarehouseData::fromWarehouse(...),
            searchUsing: self::searchBy(...),
            extra: ['site' => $selected->implode(',')],
        );

        return Inertia::render('warehouses/index', [
            'warehouses' => $warehouses,
            'filters' => $filters,
            // The form's picker: every site is somewhere a warehouse could be built.
            'locations' => OptionData::collect(Location::query()->orderBy('name')->get()),
            // The filter's options: only sites that have one. A site with no warehouse
            // is a choice that can only return nothing.
            'sitesWithWarehouses' => OptionData::collect($sites),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $warehouse = Warehouse::create($request->validated());

        $this->toast(__('warehouses.toast.created', ['name' => $warehouse->name]));

        return back();
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        $this->toast(__('warehouses.toast.updated', ['name' => $warehouse->name]));

        return back();
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $name = $warehouse->name;

        $warehouse->delete();

        $this->toast(__('warehouses.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching warehouses means. The columns live on the model — see
     * {@see Warehouse::searchableColumns()}.
     *
     * @param  Builder<Warehouse>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The sites the filter may offer: those with at least one warehouse.
     *
     * Computed over the whole table rather than the current page or search, so the
     * options do not move as you type — a filter whose choices shift under you is one
     * you cannot get back out of.
     *
     * @return Collection<int, Location>
     */
    private static function sitesWithWarehouses(): Collection
    {
        return Location::query()->whereHas('warehouses')->orderBy('name')->get();
    }
}
