<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\SavePurchaseReturn;
use App\Data\PurchaseOrderData;
use App\Data\PurchaseReturnData;
use App\Data\PurchaseReturnItemData;
use App\Data\ReturnableLineData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
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
use App\Models\User;
use App\Support\ReturnedQuantities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->with(['purchaseOrder.supplier', 'creator'])
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

    /** The return as a document: what it credits, what is going back, and what that comes to. */
    public function show(PurchaseReturn $purchaseReturn): Response
    {
        self::loadHeader($purchaseReturn);

        return Inertia::render('purchase-returns/show', [
            'return' => PurchaseReturnData::fromPurchaseReturn($purchaseReturn),
            'items' => self::lineData($purchaseReturn),
        ]);
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

        $save->handle(
            $request->returnFields(),
            $request->lines(),
            self::signedInUser($request),
            $purchaseReturn,
        );

        $this->toast(__('purchase-returns.toast.updated'));

        return to_route('purchase-returns.show', $purchaseReturn);
    }

    /**
     * Remove a return that has not happened.
     *
     * Only a pending one may go — unreachable in this slice, where every return is pending, and
     * written now because completion makes it reachable and a completed return is the source of
     * ledger rows that would then name a document nobody can open.
     */
    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        if ($purchaseReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('purchase-returns.error.completed_locked'));
        }

        $purchaseReturn->delete();

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

    /** @return list<PurchaseReturnItemData> */
    private static function lineData(PurchaseReturn $return): array
    {
        $lines = $return->items()->with('purchaseOrderItem.rawMaterial')->get();

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
        $return->load(['purchaseOrder.supplier', 'creator']);
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
