<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\RecordStockTransfer;
use App\Data\StockTransferData;
use App\Data\WarehouseOptionData;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\StockTransferRequest;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Decimals;
use App\Support\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock moving from one warehouse to another.
 *
 * **List and create only**, like the ledger it drives and for the same reason: a
 * transfer is a record of something that happened. Sent the wrong way it is corrected
 * by transferring back, which leaves both the mistake and the correction visible.
 *
 * Nothing here computes stock. {@see RecordStockTransfer} composes the two ledger rows
 * with the document that says they belong together; this controller resolves what the
 * form named, hands it over, and turns a refusal into a message on the right field.
 */
final class StockTransferController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Newest first is the order a record of events is read in, but "what were the big
     * moves" is a real question, so quantity is sortable too.
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

        $query = StockTransfer::query()
            ->with(['fromWarehouse.location', 'toWarehouse.location', 'stockable', 'user']);

        if ($selected->isNotEmpty()) {
            // Either endpoint. A transfer "involves" a warehouse whether stock left it
            // or arrived at it, and someone asking about a warehouse wants both halves —
            // the ledger already answers the one-directional question.
            $query->where(function (Builder $group) use ($selected): void {
                $group
                    ->whereIn('from_warehouse_id', $selected)
                    ->orWhereIn('to_warehouse_id', $selected);
            });
        }

        ['rows' => $transfers, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: StockTransferData::fromStockTransfer(...),
            searchUsing: self::searchBy(...),
            extra: ['warehouse' => $selected->implode(',')],
        );

        return Inertia::render('stock-transfers/index', [
            'transfers' => $transfers,
            'filters' => $filters,
            'warehouses' => $warehouses,
            'items' => $this->itemOptions(),
        ]);
    }

    /**
     * Record one transfer.
     *
     * Both warehouses and the item are re-resolved rather than trusted: validation
     * proved they exist, and this is what turns them back into the rows the action needs.
     */
    public function store(StockTransferRequest $request, RecordStockTransfer $transfer): RedirectResponse
    {
        $from = Warehouse::query()->findOrFail($request->integer('from_warehouse_id'));
        $to = Warehouse::query()->findOrFail($request->integer('to_warehouse_id'));
        $item = StockItem::decode((string) $request->string('item'));

        if ($item === null) {
            // Unreachable — the request rule just checked it. Kept because findOrFail's
            // 404 is the wrong answer for a form field, and a null here would otherwise
            // surface as a TypeError deep inside the service.
            throw ValidationException::withMessages(['item' => __('validation.exists', ['attribute' => __('validation.attributes.item')])]);
        }

        // A console super-admin is a CentralUser in another database, and `user_id`
        // points at this workspace's `users` table — see StockMovementController.
        $signedIn = $request->user();
        $user = $signedIn instanceof User ? $signedIn : null;

        try {
            $transfer->handle(
                $from,
                $to,
                $item,
                (string) $request->string('quantity'),
                $user,
                $request->input('notes'),
            );
        } catch (InsufficientStockException $e) {
            // On the quantity, not as a toast: that is the field that is wrong, and the
            // numbers are what makes it actionable. Trimmed for reading — a person is
            // about to read them, and "Only 40.5 available" beats "Only 40.5000".
            throw ValidationException::withMessages([
                'quantity' => __('stock-transfers.error.insufficient', [
                    'available' => Decimals::trim($e->available),
                    'requested' => Decimals::trim($e->requested),
                ]),
            ]);
        }

        $this->toast(__('stock-transfers.toast.recorded'));

        return back();
    }

    /**
     * What searching transfers means — see {@see StockTransfer::search()}, which covers
     * the item, both endpoints, and the notes.
     *
     * @param  Builder<StockTransfer>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
