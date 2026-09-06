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
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\WarehouseInventory;
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
 * And a detail screen, which is the odd one out: not a record but a position — what
 * this warehouse holds right now, and the level at which each item wants restocking.
 * The question spans four tables and two of them are the catalogue, so the query lives
 * in {@see WarehouseInventory} rather than here.
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

    /**
     * One warehouse, and what is in it.
     *
     * The list here is not an Eloquent one — the catalogue lives in two tables and the
     * rows are a UNION over both — so {@see RendersResourceIndex} cannot build it. The
     * service returns the same `filters` pieces the trait would, and this method
     * assembles them into the shape `ResourceFilters` promises so `DataTable` cannot
     * tell the difference.
     */
    public function show(Request $request, Warehouse $warehouse, WarehouseInventory $inventory): Response
    {
        $warehouse->load('location');

        $search = $this->queryValue($request, 'search');
        // An unrecognised scope is no scope, never an echoed one: putting it back in
        // `filters` would leave `?show=nonsense` in the URL looking like it did something.
        $show = $this->queryValue($request, 'show');
        $show = in_array($show, WarehouseInventory::SCOPES, true) ? $show : '';

        $page = $inventory->page(
            warehouse: $warehouse,
            search: $search,
            show: $show,
            sort: $this->queryValue($request, 'sort') ?: null,
            direction: $this->queryValue($request, 'direction') ?: null,
            perPage: $this->perPage($request),
        );

        return Inertia::render('warehouses/show', [
            'warehouse' => WarehouseData::fromWarehouse($warehouse),
            'items' => $page['rows'],
            'summary' => $inventory->counts($warehouse),
            // Which of the two empty states to show. An empty warehouse and an empty
            // workspace are the same picture and different problems: one is fixed by
            // moving stock in, the other by adding a product first, and offering the
            // wrong one sends somebody to a screen that cannot help them.
            'hasItems' => Product::query()->exists() || RawMaterial::query()->exists(),
            'filters' => [
                'search' => $search,
                'per_page' => $this->perPage($request),
                'sort' => $page['sort'],
                'direction' => $page['direction'],
                'sortable' => $page['sortable'],
                'extra' => array_filter(['show' => $show]),
            ],
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
