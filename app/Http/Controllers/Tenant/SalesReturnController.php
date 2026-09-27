<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\CompleteSalesReturn;
use App\Actions\DeleteSalesReturn;
use App\Actions\SaveSalesReturn;
use App\Data\ReturnableSalesLineData;
use App\Data\SalesOrderData;
use App\Data\SalesReturnData;
use App\Data\SalesReturnItemData;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ReturnExceedsOrderException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\SalesReturnRequest;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ActiveExists;
use App\Support\Decimals;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Goods a customer sent back — the paperwork, and the shelf it goes back onto.
 *
 * The mirror of {@see PurchaseReturnController}, and the class note there describes this one too:
 * a return is started from the document it credits, so {@see create()} requires `?order=`; the
 * parent is immutable once the return exists; and a lifecycle refusal is branded feedback, never
 * a bare 422.
 *
 * **Where this controller genuinely differs is what completion can be refused for.** Putting
 * stock back cannot fail for want of stock, so there is no availability prop, no panel under the
 * picker, and no shortfall message — and, following from that, **no `?warehouse_id` in the URL
 * either**. The purchase side puts the chosen warehouse in the query string because its panel is
 * refreshed by a partial reload and has to survive a full re-render after a shortfall; with
 * nothing to fetch and nothing to survive, this picker is plain local state and choosing one is
 * not a round trip.
 */
final class SalesReturnController
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
     * `total` is absent for the reason {@see PurchaseReturnController} gives: a return is
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

        $query = SalesReturn::query()
            // `completer` and `completedWarehouse` are loaded here even though no column on this
            // list shows them: SalesReturnData reads all three completion fields for every row,
            // and without them that is two lazy loads per row on a twenty-row page.
            ->with(['salesOrder.customer', 'creator', 'completer', 'completedWarehouse'])
            ->withCount('items as line_count');

        // Applied here, because `resourceList`'s `extra` only echoes a filter back into the URL
        // — it never applies one.
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
            toData: SalesReturnData::fromSalesReturn(...),
            searchUsing: self::searchBy(...),
            extra: [
                'status' => $status === null ? '' : $status->value,
                'reason' => $reason === null ? '' : $reason->value,
            ],
        );

        return Inertia::render('sales-returns/index', [
            'returns' => $returns,
            'filters' => $filters,
            // Two different nothings: an empty list because nobody has sent anything back, and
            // an empty list because nothing has ever shipped to come back from.
            'anyFulfilled' => SalesOrder::query()
                ->where('status', SalesOrderStatus::Fulfilled)
                ->exists(),
        ]);
    }

    /** Raise one against a despatch. The order comes from `?order=`; see the class note. */
    public function create(Request $request): Response|RedirectResponse
    {
        $order = self::returnableOrder((int) $this->queryValue($request, 'order'));

        if ($order === null) {
            return $this->refuseTo(__('sales-returns.error.no_order'));
        }

        if (! ReturnedQuantities::hasReturnable($order)) {
            return $this->refuseTo(__('sales-returns.error.nothing_returnable'));
        }

        return Inertia::render('sales-returns/form', [
            'return' => null,
            'order' => SalesOrderData::fromSalesOrder($order),
            'lines' => self::returnableLines($order, null),
        ]);
    }

    public function store(SalesReturnRequest $request, SaveSalesReturn $save): RedirectResponse
    {
        $return = $save->handle(
            $request->returnFields(),
            $request->lines(),
            self::signedInUser($request),
        );

        $this->toast(__('sales-returns.toast.created'));

        return to_route('sales-returns.show', $return);
    }

    /**
     * The return as a document — plus, while it is still pending, the card that takes the goods
     * back in.
     *
     * No `availability` prop and no `Request`-borne warehouse: see the class note on why this
     * side needs neither.
     */
    public function show(SalesReturn $salesReturn): Response
    {
        self::loadHeader($salesReturn);

        $pending = $salesReturn->status === ReturnStatus::Pending;

        return Inertia::render('sales-returns/show', [
            'return' => SalesReturnData::fromSalesReturn($salesReturn),
            'items' => self::lineData($salesReturn, self::returnLines($salesReturn)),
            'warehouses' => $pending ? $this->warehouseOptions() : [],
            // Opens on wherever the despatch shipped from, which is right nearly every time and
            // saves a decision nobody wants to make twice. Not pinned: returned goods routinely
            // go back to a different shelf, and sometimes to a different site.
            'chosenWarehouse' => $pending ? self::defaultWarehouse($salesReturn) : '',
        ]);
    }

    public function edit(SalesReturn $salesReturn): Response|RedirectResponse
    {
        if ($salesReturn->status !== ReturnStatus::Pending) {
            // To the document rather than the list: a bookmarked edit URL should land on the
            // screen that explains why it cannot be edited.
            $this->toast(__('sales-returns.error.not_pending'), 'error');

            return to_route('sales-returns.show', $salesReturn);
        }

        self::loadHeader($salesReturn);
        $order = $salesReturn->salesOrder->load('items.product');

        return Inertia::render('sales-returns/form', [
            'return' => SalesReturnData::fromSalesReturn($salesReturn),
            'order' => SalesOrderData::fromSalesOrder($order),
            'lines' => self::returnableLines($order, $salesReturn),
        ]);
    }

    public function update(
        SalesReturnRequest $request,
        SalesReturn $salesReturn,
        SaveSalesReturn $save,
    ): RedirectResponse {
        if ($salesReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('sales-returns.error.not_pending'));
        }

        try {
            $save->handle(
                $request->returnFields(),
                $request->lines(),
                self::signedInUser($request),
                $salesReturn,
            );
        } catch (DomainException) {
            // The check above is the ordinary one; the Action re-reads under a lock, which is
            // where a completion that landed while this form was open is actually noticed.
            return $this->refuse(__('sales-returns.error.not_pending'));
        }

        $this->toast(__('sales-returns.toast.updated'));

        return to_route('sales-returns.show', $salesReturn);
    }

    /**
     * Take the goods back in: one movement per line, into the warehouse somebody names.
     *
     * The status is checked twice and the two checks are not the same check. The one here answers
     * the ordinary case — a stale tab, a second press after a colleague — and deserves a plain
     * sentence. {@see CompleteSalesReturn} re-reads it under a lock, which is the only place the
     * true race can be settled.
     *
     * **One refusal, where the purchase side has two.** Nothing here can be short, so the only
     * thing that can stop a completion is the ceiling — and that arrives as a toast rather than a
     * field error, because no warehouse fixes it and pointing at the picker would send somebody
     * to the one control that cannot help.
     *
     * The warehouse is validated here rather than in a FormRequest of its own, for the reason
     * {@see SalesOrderController::fulfill()} gives: one field on a confirmation card, and a
     * request class would make `bun run check:validation` demand a zod schema for it. `integer`
     * is doing real work beside `exists` — without it `warehouse_id[]=7` validates and then
     * applies to row 1.
     */
    public function complete(
        Request $request,
        SalesReturn $salesReturn,
        CompleteSalesReturn $complete,
    ): RedirectResponse {
        if ($salesReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('sales-returns.error.not_pending'));
        }

        $request->validate(['warehouse_id' => ['required', 'integer', ActiveExists::of('warehouses')]]);

        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        try {
            $complete->handle($salesReturn, $warehouse, self::signedInUser($request));
        } catch (ReturnExceedsOrderException $e) {
            return $this->refuse(self::overReturnMessage($e->lines));
        } catch (InsufficientStockException) {
            // Unreachable twice over: this only ever adds, and every level row is held from
            // before the write. Caught because the service declares it, and a lock path reasoned
            // about rather than proven should not surface as a 500. Nothing was written.
            return $this->refuse(__('sales-returns.error.short_raced'));
        } catch (DomainException) {
            return $this->refuse(__('sales-returns.error.not_pending'));
        }

        $this->toast(__('sales-returns.toast.completed'));

        return back();
    }

    /**
     * Call the credit note off. Nothing moved, so nothing is unwound.
     *
     * Terminal, like completing: a cancelled return is not reopened, it is superseded by raising
     * another — which leaves both on the record instead of quietly rewriting one.
     *
     * **A consequence beyond its own column**, the one its three older siblings do not have:
     * cancelled is not in {@see ReturnStatus::consuming()}, so the quantities this return was
     * holding become returnable again the moment it saves.
     */
    public function cancel(SalesReturn $salesReturn): RedirectResponse
    {
        if ($salesReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('sales-returns.error.not_pending'));
        }

        $salesReturn->forceFill(['status' => ReturnStatus::Cancelled])->save();

        $this->toast(__('sales-returns.toast.cancelled'));

        return back();
    }

    /**
     * Remove a return that has not happened.
     *
     * Only a pending one may go: a completed return is the source of ledger rows that would
     * otherwise name a document nobody can open, and — worse — deleting it would hand the
     * despatch back room for goods that are already on the shelf.
     *
     * **The check here is the friendly one; {@see DeleteSalesReturn} takes the lock.**
     */
    public function destroy(SalesReturn $salesReturn, DeleteSalesReturn $delete): RedirectResponse
    {
        if ($salesReturn->status !== ReturnStatus::Pending) {
            return $this->refuse(__('sales-returns.error.completed_locked'));
        }

        try {
            $delete->handle($salesReturn);
        } catch (DomainException) {
            return $this->refuse(__('sales-returns.error.completed_locked'));
        }

        $this->toast(__('sales-returns.toast.deleted'));

        // Not `back()`: the document this was called from no longer renders.
        return to_route('sales-returns.index');
    }

    /**
     * Every line of the despatch this return may still touch.
     *
     * **One ceiling read, and it excludes the return being edited.** That argument is the whole
     * of the edit path's correctness: without it, opening a return that already holds 5 would
     * show a remaining of zero and refuse its own quantities.
     *
     * **A line is offered when something is left, OR when this return already holds some of it.**
     * The second half is not theoretical: a line that other returns have since finished off must
     * still render, or editing this return would silently drop it on save.
     *
     * @return list<ReturnableSalesLineData>
     */
    private static function returnableLines(SalesOrder $order, ?SalesReturn $return): array
    {
        $lines = $order->items;
        $ids = array_values($lines->map(static fn (SalesOrderItem $line): int => $line->id)->all());
        $returned = ReturnedQuantities::forSalesOrderItems($ids, $return?->id);

        $held = [];

        if ($return !== null) {
            foreach ($return->items as $item) {
                $held[$item->sales_order_item_id] = $item->quantity;
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

            $rows[] = ReturnableSalesLineData::fromOrderItem($line, $already, $quantity);
        }

        return $rows;
    }

    /**
     * The order a `?order=` names, if it can be returned against at all.
     *
     * Fulfilled only, and that rule reaches beyond this module: `OpenSalesOrder::revise()`
     * hard-deletes an order's lines on every edit, so a return pointing at a pending order's line
     * would make the sales-order edit screen fail against this module's foreign key.
     */
    private static function returnableOrder(int $id): ?SalesOrder
    {
        if ($id === 0) {
            return null;
        }

        $order = SalesOrder::query()->with(['customer', 'items.product'])->find($id);

        return $order?->status === SalesOrderStatus::Fulfilled ? $order : null;
    }

    /**
     * Where the picker opens: the warehouse the despatch shipped from, if it is still offered.
     *
     * `''` when the order has none recorded or the warehouse has since been removed, which is the
     * same nothing an unanswered picker shows.
     */
    private static function defaultWarehouse(SalesReturn $return): string
    {
        $shipped = $return->salesOrder->fulfilled_warehouse_id;

        if ($shipped === null) {
            return '';
        }

        return Warehouse::query()->whereKey($shipped)->exists() ? (string) $shipped : '';
    }

    /**
     * The return's lines, with the product each one credits.
     *
     * @return Collection<int, SalesReturnItem>
     */
    private static function returnLines(SalesReturn $return): Collection
    {
        return $return->items()->with('salesOrderItem.product')->get();
    }

    /**
     * @param  Collection<int, SalesReturnItem>  $lines
     * @return list<SalesReturnItemData>
     */
    private static function lineData(SalesReturn $return, Collection $lines): array
    {
        return array_values(
            $lines->map(static fn (SalesReturnItem $item): SalesReturnItemData => SalesReturnItemData::fromSalesReturnItem($item, $return->currency))->all(),
        );
    }

    /**
     * The over-return, as one sentence.
     *
     * Counted rather than joined: a list separator and the word order around it differ across the
     * three locales, so one line gets a named sentence and several get a number.
     *
     * Trimmed here rather than in the Action: the arithmetic keeps every place it has, and
     * display is the boundary's job.
     *
     * @param  non-empty-list<array{name: string|null, remaining: string, requested: string}>  $lines
     */
    private static function overReturnMessage(array $lines): string
    {
        $first = $lines[0];

        return trans_choice('sales-returns.error.over_return_now', count($lines), [
            // Null once the product has been force-deleted, which every other screen in this
            // module shows as the same dash. i18n-allow
            'item' => $first['name'] ?? '—',
            'remaining' => Decimals::trim($first['remaining']),
            'requested' => Decimals::trim($first['requested']),
        ]);
    }

    /**
     * What the header needs, in one round trip.
     *
     * `withCount` here as well as on the list, because {@see SalesReturnData} reads the alias and
     * a missing one reports zero lines next to a document that visibly has them.
     */
    private static function loadHeader(SalesReturn $return): void
    {
        $return->load(['salesOrder.customer', 'creator', 'completer', 'completedWarehouse']);
        $return->loadCount('items as line_count');
    }

    /**
     * What searching returns means — see {@see SalesReturn::search()}.
     *
     * @param  Builder<SalesReturn>  $query
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
     * Refuse an attempt to open a form that cannot be filled in, and send them somewhere they can
     * act — the fulfilled orders, which is where a return actually starts.
     */
    private function refuseTo(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return to_route('sales-orders.index', ['status' => SalesOrderStatus::Fulfilled->value]);
    }
}
