<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\RawMaterialData;
use App\Enums\Unit;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\RawMaterialRequest;
use App\Models\RawMaterial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Raw materials: list, create, edit, delete. Same shape as the other catalog screens —
 * one list, a dialog over it, every write returning `back()`.
 *
 * Deleting is a soft delete. A received purchase order or a posted stock movement names
 * the material it moved, and a movement that cannot say what moved is not a record.
 */
final class RawMaterialController
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
    private const SORTABLE = ['name', 'sku', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $rawMaterials, 'filters' => $filters] = $this->resourceList(
            request: $request,
            // `products` is the bill-of-materials usage — see RawMaterial::products().
            // Eager-loaded so the delete guard can be explained on the row rather than
            // discovered by pressing the button.
            query: RawMaterial::query()->with(['creator', 'products']),
            sortable: self::SORTABLE,
            toData: RawMaterialData::fromRawMaterial(...),
            searchUsing: self::searchBy(...),
        );

        return Inertia::render('raw-materials/index', [
            'rawMaterials' => $rawMaterials,
            'filters' => $filters,
            // Codes grouped by what they measure — the shape the picker renders, and
            // the list the browser validates against. The words are user-facing strings
            // and live in lang/{locale}/units.php, keyed by these codes; sending
            // English labels would ship one language inside the data.
            'units' => Unit::grouped(),
        ]);
    }

    public function store(RawMaterialRequest $request): RedirectResponse
    {
        $rawMaterial = RawMaterial::create($request->validated());

        $this->toast(__('raw-materials.toast.created', ['name' => $rawMaterial->name]));

        return back();
    }

    public function update(RawMaterialRequest $request, RawMaterial $rawMaterial): RedirectResponse
    {
        $rawMaterial->update($request->validated());

        $this->toast(__('raw-materials.toast.updated', ['name' => $rawMaterial->name]));

        return back();
    }

    /**
     * Delete a material — unless a product is still built from it.
     *
     * The guard exists because `bom_items.raw_material_id` is NOT NULL, unlike the
     * nullable keys a product uses for its category and supplier. Those can lose their
     * row and simply show a dash; a bill line cannot. Deleting anyway leaves a bill
     * pointing at something that is not there, which the editor cannot render and the
     * validator will not let anyone save — so the bill is stuck, and the reason is two
     * screens away. Refusing here is the cheaper half of that trade.
     *
     * Soft-deleted products do not count; see {@see RawMaterial::products()} on why.
     */
    public function destroy(RawMaterial $rawMaterial): RedirectResponse
    {
        $usedBy = $rawMaterial->products()->pluck('name');

        if ($usedBy->isNotEmpty()) {
            $this->toast(
                trans_choice('raw-materials.toast.in_use', $usedBy->count(), [
                    'name' => $rawMaterial->name,
                    'count' => $usedBy->count(),
                    'products' => $usedBy->implode(', '),
                ]),
                'error',
            );

            return back();
        }

        $name = $rawMaterial->name;

        $rawMaterial->delete();

        $this->toast(__('raw-materials.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching raw materials means. The columns live on the model — see
     * {@see RawMaterial::searchableColumns()}.
     *
     * @param  Builder<RawMaterial>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
