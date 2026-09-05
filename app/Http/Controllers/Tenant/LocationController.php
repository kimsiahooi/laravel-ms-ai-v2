<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\LocationData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\LocationRequest;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sites: list, create, edit, delete. Same shape as the catalog screens — one list, a
 * dialog over it, every write returning `back()`.
 *
 * Deleting is a soft delete. A movement that happened here names the site it happened
 * at, and a movement that cannot say where it happened is not a record.
 *
 * A site with warehouses on it cannot be deleted — see {@see destroy()}.
 */
final class LocationController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by. This list is the SQL-injection guard for
     * `?sort=` — see {@see SortsResourceQuery} — and it also decides which headers the
     * table renders as clickable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'code', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $locations, 'filters' => $filters] = $this->resourceList(
            request: $request,
            // `warehouses` is eager-loaded for the same reason `creator` is — both are
            // read on every row, and without it the list is two queries per row. The
            // warehouses are what the delete guard explains itself with, so the row can
            // say why Delete will refuse before anyone presses it.
            query: Location::query()->with(['creator', 'warehouses']),
            sortable: self::SORTABLE,
            toData: LocationData::fromLocation(...),
            searchUsing: self::searchBy(...),
        );

        return Inertia::render('locations/index', [
            'locations' => $locations,
            'filters' => $filters,
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $location = Location::create($request->validated());

        $this->toast(__('locations.toast.created', ['name' => $location->name]));

        return back();
    }

    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        $this->toast(__('locations.toast.updated', ['name' => $location->name]));

        return back();
    }

    /**
     * Delete a site — unless warehouses still stand on it.
     *
     * `warehouses.location_id` is NOT NULL and `restrictOnDelete`, so the database
     * would refuse a hard delete outright. A soft delete slips past that: the row
     * stays, the constraint is never tested, and the warehouses are left pointing at a
     * site the workspace believes it removed — visible nowhere, and still holding
     * stock once there is stock to hold. Refusing here is the cheaper half of that.
     *
     * Trashed warehouses do not count; see {@see Location::warehouses()} on why.
     */
    public function destroy(Location $location): RedirectResponse
    {
        $standing = $location->warehouses()->pluck('name');

        if ($standing->isNotEmpty()) {
            $this->toast(
                trans_choice('locations.toast.in_use', $standing->count(), [
                    'name' => $location->name,
                    'count' => $standing->count(),
                    'warehouses' => $standing->implode(', '),
                ]),
                'error',
            );

            return back();
        }

        $name = $location->name;

        $location->delete();

        $this->toast(__('locations.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching sites means. The columns live on the model — see
     * {@see Location::searchableColumns()} — because they describe the data, not
     * this request.
     *
     * @param  Builder<Location>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
