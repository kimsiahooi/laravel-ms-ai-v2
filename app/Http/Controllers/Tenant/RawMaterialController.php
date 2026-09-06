<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\RawMaterialData;
use App\Enums\Unit;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\RawMaterialRequest;
use App\Models\BusinessSetting;
use App\Models\RawMaterial;
use App\Support\Money;
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
    use ReadsQueryValues;
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
        // The unit filter. Resolved through the enum, so `?unit=nonsense` is simply no
        // filter rather than an empty list or an error — there is nothing to protect
        // here beyond the column, and tryFrom is the whole of that.
        $unit = Unit::tryFrom($this->queryValue($request, 'unit'));

        // `products` is the bill-of-materials usage — see RawMaterial::products().
        // Eager-loaded so the delete guard can be explained on the row rather than
        // discovered by pressing the button.
        $query = RawMaterial::query()->with(['creator', 'products']);

        if ($unit !== null) {
            $query->where('unit', $unit);
        }

        // One read for both money props below: current() is a firstOrCreate, and
        // calling it twice would be two round trips for one row.
        $settings = BusinessSetting::current();

        ['rows' => $rawMaterials, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: RawMaterialData::fromRawMaterial(...),
            searchUsing: self::searchBy(...),
            extra: ['unit' => $unit === null ? '' : $unit->value],
        );

        return Inertia::render('raw-materials/index', [
            'rawMaterials' => $rawMaterials,
            'filters' => $filters,
            // Codes grouped by what they measure — the shape the picker renders, and
            // the list the browser validates against. The words are user-facing strings
            // and live in lang/{locale}/units.php, keyed by these codes; sending
            // English labels would ship one language inside the data.
            'units' => Unit::grouped(),
            // Just the codes the unit filter may offer — see unitsInUse().
            'unitsInUse' => self::unitsInUse(),
            // The currency the default cost is quoted in. One number per
            // material, always in the workspace's base currency: the catalogue holds a
            // suggestion, and an order that is raised in another currency records what
            // was actually agreed on its own line. Sent so the field can say which money
            // it is asking for rather than leave it to be guessed.
            'baseCurrency' => $settings->base_currency,
            // How many decimal places that currency actually has, from
            // Money::scaleFor — so the browser can pad `12.5` to `12.50` without
            // holding its own copy of which currencies divide by a hundred. A stored
            // figure with more places than this keeps them: see formatMoney.
            'baseCurrencyScale' => Money::scaleFor($settings->base_currency),
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

    /**
     * The unit codes this workspace's raw materials actually use, in the enum's own order.
     *
     * Only the units in use, so the filter never offers a choice that returns nothing
     * — there are fourteen units and a workspace typically uses three. Computed over
     * the whole table rather than the current page or the current search: a filter
     * whose options moved as you searched would be a filter you could not get back out
     * of.
     *
     * Enum order rather than alphabetical, so the list reads mass, volume, length,
     * count instead of interleaving them.
     *
     * @return list<string>
     */
    private static function unitsInUse(): array
    {
        $used = RawMaterial::query()->distinct()->pluck('unit')
            ->map(static fn (Unit $unit): string => $unit->value)
            ->all();

        return array_values(array_filter(
            array_map(static fn (Unit $unit): string => $unit->value, Unit::cases()),
            static fn (string $code): bool => in_array($code, $used, true),
        ));
    }
}
