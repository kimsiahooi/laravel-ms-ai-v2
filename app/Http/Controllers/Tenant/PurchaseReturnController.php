<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\CompletePurchaseReturn;
use App\Actions\DeletePurchaseReturn;
use App\Actions\SavePurchaseReturn;
use App\Data\PurchaseOrderData;
use App\Data\PurchaseReturnData;
use App\Data\PurchaseReturnItemData;
use App\Data\ReturnableLineData;
use App\Data\StockAvailabilityData;
use App\Data\WarehouseOptionData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InsufficientStockForOrderException;
use App\Exceptions\ReturnExceedsOrderException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\PurchaseReturnRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\ActiveExists;
use App\Support\Decimals;
use App\Support\OrderAvailability;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Goods going back to a supplier — the paperwork, not the movement.
 *
 * **This controller never touches stock.** Raising, editing and deleting a return move nothing;
 * the ledger is written when the return is *completed*, which is the next slice. Until then a
 * return is a claim, and the only thing enforced here is that the claim is possible:
 * {@see ReturnedQuantities} caps every line at what the delivery has left.
 *
 * **A return is started from the order it credits**, so {@see create()} requires `?order=`.
 * There is no picker: a workspace accumulates received orders forever, unlike its catalogue, so
 * a page that offered all of them would ship a list nobody capped. The order's own screen is
 * where somebody already is when they decide to send something back.
 *
 * **The parent is immutable once the return exists.** Changing it would invalidate every copied
 * price and every line reference, which is the same work as raising a new return — so
 * `PurchaseReturnRequest` pins the field and {@see SavePurchaseReturn} reads it off the return
 * rather than the payload. Between them it is not a thing that can be expressed.
 *
 * A lifecycle refusal is branded feedback, never a bare 422 — the rule
 * {@see PurchaseOrderController} states.
 */
final class PurchaseReturnController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by — the SQL-injection guard for `?sort=`.
     *
     * `total` is absent for the reason {@see PurchaseOrderController} gives: a return is
     * denominated in the order's currency, so ordering the column would rank 900 MYR above
     * 500 USD and present it as an answer. The order's number is absent because it lives on
     * another table.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['number', 'status', 'created_at'];

    /** The scale of `decimal(15,4)` — the quantity columns', and {@see ReturnedQuantities}'s. */
    private const SCALE = 4;

    public function index(Request $request): Response
    {
        $status = ReturnStatus::tryFrom($this->queryValue($request, 'status'));
        $reason = ReturnReason::tryFrom($this->queryValue($request, 'reason'));

        $query = PurchaseReturn::query()
            // `completer` and `completedWarehouse` are loaded here even though no column on
            // this list shows them: PurchaseReturnData reads all three completion fields for
            // every row, and without them that is two lazy loads per row on a twenty-row page.
            ->with(['purchaseOrder.supplier', 'creator', 'completer', 'completedWarehouse'])
            ->withCount('items as line_count');

        // Applied here, because `resourceList`'s `extra` only echoes a filter back into the
        // URL — it never applies one.
        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($reason !== null) {
            $query->where('reason', $reason);
        }

        ['rows' => $returns, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: PurchaseReturnData::fromPurchaseReturn(...),
            searchUsing: self::searchBy(...),
            extra: [
                'status' => $status === null ? '' : $status->value,
                'reason' => $reason === null ? '' : $reason->value,
            ],
        );

        return Inertia::render('purchase-returns/index', [
            'returns' => $returns,
            'filters' => $filters,
            // Two different nothings: an empty list because nobody has returned anything, and
            // an empty list because nothing has ever been received to return. The advice that
            // helps is different in each case.
            'anyReceived' => PurchaseOrder::query()
                ->where('status', PurchaseOrderStatus::Received)
                ->exists(),
        ]);
    }

    /** Raise one against a delivery. The order comes from `?order=`; see the class note. */
    public function create(Request $request): Response|RedirectResponse
    {
        $order = self::returnableOrder((int) $this->queryValue($request, 'order'));

        if ($order === null) {
            return $this->refuseTo(__('purchase-returns.error.no_order'));
        }

        if (! ReturnedQuantities::hasReturnable($order)) {
            return $this->refuseTo(__('purchase-returns.error.nothing_returnable'));
        }

        return Inertia::render('purchase-returns/form', [
            'return' => null,
            'order' => PurchaseOrderData::fromPurchaseOrder($order),
            'lines' => self::returnableLines($order, null),
        ]);
    }

    public function store(PurchaseReturnRequest $request, SavePurchaseReturn $save): RedirectResponse
    {
        $return = $save->handle(
            $request->returnFields(),
            $request->lines(),
            self::signedInUser($request),
        );

        $this->toast(__('purchase-returns.toast.created'));

        return to_route('purchase-returns.show', $return);
    }

    /**
     * The return as a document: what it credits, what is going back, and what that comes to —
     * plus, while it is still pending, the card that sends the goods.
     *
     * **The chosen warehouse rides in the query string rather than an optional prop**, and the
     * reason is the same one {@see SalesOrderController::show()} spells out: the panel is
     * refreshed with a partial reload, which is what an optional prop is for, but a shortfall
     * comes back as a `ValidationException` that redirects here and re-renders in *full* — and a
     * full render excludes an optional prop by definition. The panel would go blank at the exact
     * moment somebody needs to read it.
     *
     * A completed or cancelled return gets neither prop: there is nothing left to choose.
     */
    public function show(Request $request, PurchaseReturn $purchaseReturn, StockService $stock): Response
    {
        self::loadHeader($purchaseReturn);

        // Read once and used twice — the table renders them and the panel adds them up.
        $lines = self::returnLines($purchaseReturn);

        $pending = $purchaseReturn->status === ReturnStatus::Pending;
        $warehouses = $pending ? $this->warehouseOptions() : [];
        $warehouse = $pending ? $this->chosenWarehouse($request, $warehouses, $purchaseReturn) : null;

        return Inertia::render('purchase-returns/show', [
            'return' => PurchaseReturnData::fromPurchaseReturn($purchaseReturn),
            'items' => self::lineData($purchaseReturn, $lines),
            'warehouses' => $warehouses,
            'chosenWarehouse' => $warehouse === null ? '' : (string) $warehouse->id,
            'availability' => $warehouse === null
                ? null
                : self::availability($lines, $warehouse, $stock),
        ]);
    }

    /**
     * Send the goods back: one movement per line, out of the warehouse somebody names.
     *
     * The status is checked twice and the two checks are not the same check. The one here
     * answers the ordinary case — a stale tab, a second press after a colleague — and deserves a
     * plain sentence. {@see CompletePurchaseReturn} re-reads it under a lock, which is the only
     * place the true race can be settled.
     *
     * **Two refusals, and they want different treatment.** A shortfall is filed on
     * `warehouse_id`, because that is the one control the card has and changing it is the action
     * that might make the message go away. An over-return is a toast instead: no warehouse fixes
     * it, and pointing at the picker would send somebody to the one control that cannot help.
     *
     * The warehouse is validated here rather than in a FormRequest of its own, for the reason
     * {@see SalesOrderController::fulfill()} gives: one field on a confirmation card, and a
     * request class would make `bun run check:validation` demand a zod schema for it. `integer`
     * is doing real work beside `exists` — without it `warehouse_id[]=7` validates and then
     * applies to row 1.
     */
    public function complete(
        Request $request,
        PurchaseReturn $purchaseReturn,
        CompletePurchaseReturn $complete,
    ): RedirectResponse {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('purchase-returns.error.not_pending'));
        }

        $request->validate(['warehouse_id' => ['required', 'integer', ActiveExists::of('warehouses')]]);

        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        try {
            $complete->handle($purchaseReturn, $warehouse, self::signedInUser($request));
        } catch (InsufficientStockForOrderException $e) {
            throw ValidationException::withMessages([
                'warehouse_id' => self::shortfallMessage($e->shortfalls),
            ]);
        } catch (ReturnExceedsOrderException $e) {
            return $this->refuse(self::overReturnMessage($e->lines));
        } catch (InsufficientStockException) {
            // Unreachable: every level row is held under `FOR UPDATE` from before the check —
            // see the Action. Caught because the service declares it, and a lock path reasoned
            // about rather than proven should not surface as a 500. Nothing was written.
            return $this->refuse(__('purchase-returns.error.short_raced'));
        } catch (DomainException) {
            return $this->refuse(__('purchase-returns.error.not_pending'));
        }

        $this->toast(__('purchase-returns.toast.completed'));

        return back();
    }

    /**
     * Call the return off. Nothing moved, so nothing is unwound.
     *
     * Terminal, like completing: a cancelled return is not reopened, it is superseded by raising
     * another — which leaves both on the record instead of quietly rewriting one.
     *
     * **The one cancel in this app with a consequence beyond its own column.** Cancelled is not
     * in {@see ReturnStatus::consuming()}, so the quantities this return was holding become
     * returnable again the moment it saves. That is the intended effect and the reason somebody
     * presses it — but it is worth saying, because the three cancels this one is modelled on all
     * do nothing but close a document.
     */
    public function cancel(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('purchase-returns.error.not_pending'));
        }

        $purchaseReturn->forceFill(['status' => ReturnStatus::Cancelled])->save();

        $this->toast(__('purchase-returns.toast.cancelled'));

        return back();
    }

    public function edit(PurchaseReturn $purchaseReturn): Response|RedirectResponse
    {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            // To the document rather than the list: a bookmarked edit URL should land on the
            // screen that explains why it cannot be edited.
            $this->toast(__('purchase-returns.error.not_pending'), 'error');

            return to_route('purchase-returns.show', $purchaseReturn);
        }

        self::loadHeader($purchaseReturn);
        $order = $purchaseReturn->purchaseOrder->load('items.rawMaterial');

        return Inertia::render('purchase-returns/form', [
            'return' => PurchaseReturnData::fromPurchaseReturn($purchaseReturn),
            'order' => PurchaseOrderData::fromPurchaseOrder($order),
            'lines' => self::returnableLines($order, $purchaseReturn),
        ]);
    }

    public function update(
        PurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn,
        SavePurchaseReturn $save,
    ): RedirectResponse {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('purchase-returns.error.not_pending'));
        }

        try {
            $save->handle(
                $request->returnFields(),
                $request->lines(),
                self::signedInUser($request),
                $purchaseReturn,
            );
        } catch (DomainException) {
            // The check above is the ordinary one; the Action re-reads under a lock, which is
            // where a completion that landed while this form was open is actually noticed.
            return $this->refuse(__('purchase-returns.error.not_pending'));
        }

        $this->toast(__('purchase-returns.toast.updated'));

        return to_route('purchase-returns.show', $purchaseReturn);
    }

    /**
     * Remove a return that has not happened.
     *
     * Only a pending one may go: a completed return is the source of ledger rows that would
     * otherwise name a document nobody can open, and — worse — deleting it would hand the
     * delivery back quantity that has physically left the building.
     *
     * **The check here is the friendly one; {@see DeletePurchaseReturn} takes the lock.** This
     * used to be the only check, which was safe only while every return was pending. Completion
     * is what made the race real, so the delete moved into an Action that re-reads the status
     * under the same lock the completion holds.
     */
    public function destroy(PurchaseReturn $purchaseReturn, DeletePurchaseReturn $delete): RedirectResponse
    {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('purchase-returns.error.completed_locked'));
        }

        try {
            $delete->handle($purchaseReturn);
        } catch (DomainException) {
            return $this->refuse(__('purchase-returns.error.completed_locked'));
        }

        $this->toast(__('purchase-returns.toast.deleted'));

        // Not `back()`: the document this was called from no longer renders.
        return to_route('purchase-returns.index');
    }

    /**
     * Every line of the delivery this return may still touch.
     *
     * **One ceiling read, and it excludes the return being edited.** That argument is the whole
     * of the edit path's correctness: without it, opening a return that already holds 5 would
     * show a remaining of zero and refuse its own quantities. With it, the number on screen is
     * the number `PurchaseReturnRequest`'s after-hook will enforce — the two agree by
     * construction rather than by coincidence.
     *
     * **A line is offered when something is left, OR when this return already holds some of
     * it.** The second half is not theoretical: a line that other returns have since finished
     * off must still render, or editing this return would silently drop it on save.
     *
     * @return list<ReturnableLineData>
     */
    private static function returnableLines(PurchaseOrder $order, ?PurchaseReturn $return): array
    {
        $lines = $order->items;
        $ids = array_values($lines->map(static fn (PurchaseOrderItem $line): int => $line->id)->all());
        $returned = ReturnedQuantities::forOrderItems($ids, $return?->id);

        $held = [];

        if ($return !== null) {
            foreach ($return->items as $item) {
                $held[$item->purchase_order_item_id] = $item->quantity;
            }
        }

        $rows = [];

        foreach ($lines as $line) {
            $already = $returned[$line->id] ?? '0';
            $quantity = $held[$line->id] ?? '';
            $left = ReturnedQuantities::remaining($line->quantity, $already);

            if (bccomp($left, '0', self::SCALE) <= 0 && $quantity === '') {
                continue;
            }

            $rows[] = ReturnableLineData::fromOrderItem($line, $already, $quantity);
        }

        return $rows;
    }

    /**
     * The order a `?order=` names, if it can be returned against at all.
     *
     * Received only, and that rule reaches beyond this module: `OpenPurchaseOrder::revise()`
     * hard-deletes an order's lines on every edit, so a return pointing at a pending order's
     * line would make the purchase-order edit screen fail against this module's foreign key.
     */
    private static function returnableOrder(int $id): ?PurchaseOrder
    {
        if ($id === 0) {
            return null;
        }

        $order = PurchaseOrder::query()->with(['supplier', 'items.rawMaterial'])->find($id);

        return $order?->status === PurchaseOrderStatus::Received ? $order : null;
    }

    /**
     * Which warehouse the card is asking about.
     *
     * **Validated against the list this page offered, not against the table** — the same
     * narrowing {@see SalesOrderController::chosenWarehouse()} does. A crafted id that exists
     * but was never on the picker falls through to the default rather than being honoured.
     *
     * **Defaulted to where the delivery landed, and not pinned to it.** Opening on the order's
     * `received_warehouse_id` is right nearly every time and saves a decision nobody wants to
     * make twice; refusing everything else would be wrong, because stock gets transferred
     * between sites after receipt and the goods may simply not be there any more.
     *
     * @param  list<WarehouseOptionData>  $warehouses
     */
    private function chosenWarehouse(Request $request, array $warehouses, PurchaseReturn $return): ?Warehouse
    {
        $offered = array_map(
            static fn (WarehouseOptionData $warehouse): int => $warehouse->id,
            $warehouses,
        );

        $requested = (int) $this->queryValue($request, 'warehouse_id');

        if (in_array($requested, $offered, true)) {
            return Warehouse::query()->find($requested);
        }

        $landed = $return->purchaseOrder->received_warehouse_id;

        return $landed !== null && in_array($landed, $offered, true)
            ? Warehouse::query()->find($landed)
            : null;
    }

    /**
     * What the chosen warehouse holds against what this return wants to take out.
     *
     * **The same function the Action checks with**, so a green row and a refusal cannot disagree
     * — only the lock differs, and that difference is the point: this reads levels without one,
     * so the numbers are stale the moment they arrive, which is why the button above them is
     * never disabled by them.
     *
     * Added up per material rather than per line, because two order lines may name one material.
     *
     * @param  Collection<int, PurchaseReturnItem>  $lines
     * @return list<StockAvailabilityData>
     */
    private static function availability(Collection $lines, Warehouse $warehouse, StockService $stock): array
    {
        $required = OrderAvailability::demandsFrom(
            $lines,
            static fn (PurchaseReturnItem $line): ?RawMaterial => $line->purchaseOrderItem->rawMaterial,
        );

        return OrderAvailability::rows(
            $required,
            $stock->onHandFor($warehouse, OrderAvailability::items($required)),
        );
    }

    /**
     * The shortfall, as one sentence.
     *
     * Counted rather than joined: a list separator and the word order around it differ across
     * the three locales, so one short material gets a named sentence and several get a number
     * plus a pointer at the panel, which is already listing them in rows.
     *
     * @param  non-empty-list<StockAvailabilityData>  $shortfalls
     */
    private static function shortfallMessage(array $shortfalls): string
    {
        $first = $shortfalls[0];

        return trans_choice('purchase-returns.error.short', count($shortfalls), [
            'item' => $first->name,
            // Already display strings — StockAvailabilityData trims them on the way out.
            'available' => $first->on_hand,
            'required' => $first->required,
        ]);
    }

    /**
     * The over-return, as one sentence, for the same reason and in the same shape.
     *
     * Trimmed here rather than in the Action: the arithmetic keeps every place it has, and
     * display is the boundary's job — the rule {@see StockService} states.
     *
     * @param  non-empty-list<array{name: string|null, remaining: string, requested: string}>  $lines
     */
    private static function overReturnMessage(array $lines): string
    {
        $first = $lines[0];

        return trans_choice('purchase-returns.error.over_return_now', count($lines), [
            // Null once the material has been force-deleted, which every other screen in this
            // module shows as the same dash. i18n-allow
            'item' => $first['name'] ?? '—',
            'remaining' => Decimals::trim($first['remaining']),
            'requested' => Decimals::trim($first['requested']),
        ]);
    }

    /**
     * The return's lines, with the material each one credits.
     *
     * Its own method because {@see show()} reads them once and two things consume them — the
     * table renders each line, the availability panel adds them up per material.
     *
     * @return Collection<int, PurchaseReturnItem>
     */
    private static function returnLines(PurchaseReturn $return): Collection
    {
        return $return->items()->with('purchaseOrderItem.rawMaterial')->get();
    }

    /**
     * @param  Collection<int, PurchaseReturnItem>  $lines
     * @return list<PurchaseReturnItemData>
     */
    private static function lineData(PurchaseReturn $return, Collection $lines): array
    {
        return array_values(
            $lines->map(static fn (PurchaseReturnItem $item): PurchaseReturnItemData => PurchaseReturnItemData::fromPurchaseReturnItem($item, $return->currency))->all(),
        );
    }

    /**
     * What the header needs, in one round trip.
     *
     * `withCount` here as well as on the list, because {@see PurchaseReturnData} reads the alias
     * and a missing one reports zero lines next to a document that visibly has them.
     */
    private static function loadHeader(PurchaseReturn $return): void
    {
        $return->load(['purchaseOrder.supplier', 'creator', 'completer', 'completedWarehouse']);
        $return->loadCount('items as line_count');
    }

    /**
     * What searching returns means — see {@see PurchaseReturn::search()}, which covers this
     * document's number, the order's, the supplier and the notes.
     *
     * @param  Builder<PurchaseReturn>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The signed-in person, or nobody.
     *
     * A console super-admin is a `CentralUser` in another database — see
     * {@see SalesOrderController::signedInUser()} for the same narrowing and the same reason.
     */
    private static function signedInUser(Request $request): ?User
    {
        $signedIn = $request->user();

        return $signedIn instanceof User ? $signedIn : null;
    }

    /** Refuse a lifecycle action in this app's own voice, never a bare 422. */
    private function refuse(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return back();
    }

    /**
     * Refuse an attempt to open a form that cannot be filled in, and send them somewhere they
     * can act — the received orders, which is where a return actually starts.
     */
    private function refuseTo(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return to_route('purchase-orders.index', ['status' => PurchaseOrderStatus::Received->value]);
    }
}
