<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\StockMovementData;
use App\Data\WarehouseOptionData;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\StockMovementRequest;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Decimals;
use App\Support\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The stock ledger: what moved, where, and why.
 *
 * **List and create only, and that is the whole design.** There is no edit and no
 * delete, because the ledger is append-only — a mistake is corrected by recording the
 * opposite movement, which leaves both the mistake and the correction visible. An
 * editable ledger is a ledger nobody can rely on.
 *
 * Nothing here computes stock. Every write goes through {@see StockService}, which is
 * the only thing allowed to touch `warehouse_stocks`; this controller resolves what the
 * form named, hands it over, and turns a refusal into a message on the right field.
 */
final class StockMovementController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Newest first is the only order that makes sense for a ledger, but quantity is
     * worth sorting by — "what were the big movements" is a real question.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['quantity', 'created_at'];

    public function index(Request $request): Response
    {
        $warehouses = $this->warehouseOptions();
        $known = array_map(static fn (WarehouseOptionData $w): int => $w->id, $warehouses);

        $selected = collect(explode(',', $this->queryValue($request, 'warehouse')))
            ->map(static fn (string $id): int => (int) trim($id))
            ->filter(static fn (int $id): bool => in_array($id, $known, true))
            ->unique()
            ->values();

        // Several at once, meaning ANY of them — the same reading as the warehouse
        // filter beside it. Unknown values are dropped rather than refused: a stale
        // bookmark should narrow by what it still recognises, not 500.
        $reasons = collect(explode(',', $this->queryValue($request, 'reason')))
            ->map(static fn (string $value): ?StockMovementReason => StockMovementReason::tryFrom(trim($value)))
            ->filter()
            ->unique()
            ->values();

        $query = StockMovement::query()->with(['warehouse.location', 'stockable', 'user']);

        if ($selected->isNotEmpty()) {
            $query->whereIn('warehouse_id', $selected);
        }

        if ($reasons->isNotEmpty()) {
            $query->whereIn('reason', $reasons);
        }

        ['rows' => $movements, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: StockMovementData::fromStockMovement(...),
            searchUsing: self::searchBy(...),
            extra: [
                'warehouse' => $selected->implode(','),
                'reason' => $reasons->map(static fn (StockMovementReason $r): string => $r->value)->implode(','),
            ],
        );

        return Inertia::render('stock-movements/index', [
            'movements' => $movements,
            'filters' => $filters,
            'warehouses' => $warehouses,
            'items' => $this->itemOptions(),
            // The reasons the filter may offer: the ones actually present, so the
            // control never lists a choice that returns nothing. The form has no reason
            // picker — the type toggle decides it, and every manual movement is an
            // Adjustment — so StockMovementReason::manual() has no consumer here.
            'reasonsUsed' => self::reasonsUsed(),
        ]);
    }

    /**
     * Record one movement.
     *
     * The three types are three different questions. `in` and `out` say how much moved;
     * `set` says what the level is now and lets the service work out the difference
     * under the lock — which is why a stock count cannot race another one.
     */
    public function store(StockMovementRequest $request, StockService $stock): RedirectResponse
    {
        // Both are re-resolved rather than trusted: validation proved they exist, and
        // this is what turns them back into the rows the service needs.
        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));
        $item = StockItem::decode((string) $request->string('item'));

        if ($item === null) {
            // Unreachable — the request rule just checked it. Kept because findOrFail's
            // 404 is the wrong answer for a form field, and a null here would otherwise
            // surface as a TypeError deep inside the service.
            throw ValidationException::withMessages(['item' => __('validation.exists', ['attribute' => __('validation.attributes.item')])]);
        }

        $quantity = (string) $request->string('quantity');
        $notes = $request->input('notes');

        // The console's super-admins are CentralUser rows in another database, and
        // `stock_movements.user_id` points at this workspace's `users` table. Narrowing
        // rather than casting: a console user recording a movement would be a foreign
        // key into the wrong database, so the honest answer is that nobody is named.
        $signedIn = $request->user();
        $user = $signedIn instanceof User ? $signedIn : null;

        try {
            match ($request->string('type')->value()) {
                'in' => $stock->record($warehouse, $item, $quantity, StockMovementReason::Adjustment, $user, $notes),
                'out' => $stock->record($warehouse, $item, '-'.$quantity, StockMovementReason::Adjustment, $user, $notes),
                default => $stock->setLevel($warehouse, $item, $quantity, StockMovementReason::Adjustment, $user, $notes),
            };
        } catch (InsufficientStockException $e) {
            // On the quantity, not as a toast: it is that field that is wrong, and the
            // numbers are what makes it actionable — see the exception.
            // Trimmed here rather than in the service: the numbers are being read by a
            // person now, and "Only 40.5 available" beats "Only 40.5000 available".
            // The service keeps full scale because it is doing arithmetic with them.
            throw ValidationException::withMessages([
                'quantity' => __('stock-movements.error.insufficient', [
                    'available' => Decimals::trim($e->available),
                    'requested' => Decimals::trim($e->requested),
                ]),
            ]);
        }

        $this->toast(__('stock-movements.toast.recorded'));

        return back();
    }

    /**
     * What searching the ledger means: the item that moved, the warehouse it moved
     * through, and whatever somebody typed in the notes.
     *
     * `whereHasMorph` rather than a join — a movement must appear once, and the two
     * stockable tables cannot be joined to in one pass anyway.
     *
     * @param  Builder<StockMovement>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The reasons this workspace's ledger actually contains, in the enum's own order.
     *
     * Computed over the whole table rather than the page, so the filter's choices do
     * not move as you search.
     *
     * @return list<string>
     */
    private static function reasonsUsed(): array
    {
        $used = StockMovement::query()->distinct()->pluck('reason')
            ->map(static fn (StockMovementReason $reason): string => $reason->value)
            ->all();

        return array_values(array_filter(
            array_map(static fn (StockMovementReason $r): string => $r->value, StockMovementReason::cases()),
            static fn (string $value): bool => in_array($value, $used, true),
        ));
    }
}
