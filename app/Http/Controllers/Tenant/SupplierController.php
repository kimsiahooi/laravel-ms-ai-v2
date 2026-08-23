<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\SupplierData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Suppliers: list, create, edit, delete. Same shape as categories — one screen, a
 * dialog over the list, every write returning `back()` so the table refreshes in
 * place.
 *
 * Deleting is a soft delete. Purchase orders will reference suppliers, and a purchase
 * order that cannot name who it was raised against is not a record of anything.
 */
final class SupplierController
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
    private const SORTABLE = ['name', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $suppliers, 'filters' => $filters] = $this->resourceList(
            request: $request,
            // Eager-loaded: the "Created by" column reads it on every row, and without
            // this the list is one query per row.
            query: Supplier::query()->with('creator'),
            sortable: self::SORTABLE,
            toData: SupplierData::fromSupplier(...),
            searchUsing: self::searchBy(...),
        );

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        $this->toast(__('suppliers.toast.created', ['name' => $supplier->name]));

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        $this->toast(__('suppliers.toast.updated', ['name' => $supplier->name]));

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $name = $supplier->name;

        $supplier->delete();

        $this->toast(__('suppliers.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching suppliers means. The columns live on the model — see
     * {@see Supplier::searchableColumns()} — because they describe the data, not
     * this request.
     *
     * @param  Builder<Supplier>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
