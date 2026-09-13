<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\FulfillSalesOrder;
use App\Actions\OpenSalesOrder;
use App\Data\OptionData;
use App\Data\SalesOrderData;
use App\Data\SalesOrderItemData;
use App\Data\StockAvailabilityData;
use App\Data\StockItemOptionData;
use App\Data\StockTakeData;
use App\Data\WarehouseOptionData;
use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InsufficientStockForOrderException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\SalesOrderRequest;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\ActiveExists;
use App\Support\Decimals;
use App\Support\OrderAvailability;
use App\Support\OrderTotals;
use App\Support\StockItem;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Goods sold to a customer: taken, corrected while pending, then fulfilled once.
 *
 * The mirror of {@see PurchaseOrderController}, and shaped the same way for the same reason
 * — an order is a header and a grid of priced rows that a person works across, which is not
 * something a modal over a list can hold. So there is a create page, an edit page and a
 * detail page.
 *
 * Nothing here computes money and nothing here writes stock. {@see OpenSalesOrder} composes
 * the document, its lines and the totals {@see OrderTotals} decides. This controller resolves
 * what the screen named, hands it over, and turns a refusal into a message a person can read.
 *
 * **Fulfilment is the one thing here that can fail on the goods rather than on the form**,
 * which receiving never can, and it shows in three places: the availability panel on the
 * detail page, the shortfall message a refusal turns into, and {@see FulfillSalesOrder}'s lock.
 * The panel and the Action reach their answer through the same {@see OrderAvailability}, so a
 * green row and a refusal cannot disagree about the same shelf.
 *
 * **A lifecycle refusal is branded feedback, never a bare 422.** Editing an order that has
 * shipped is an ordinary thing to arrive at from a stale tab, so it leaves as an error toast.
 * v1 used `abort_unless(..., 422)`, which surfaces as Inertia's raw error modal: the wrong
 * register for "somebody beat you to it", and untranslatable besides.
 */
final class SalesOrderController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by. This list is the SQL-injection guard for `?sort=`
     * — see {@see SortsResourceQuery} — and it decides which headers the table renders as
     * clickable.
     *
     * **`total` is deliberately absent.** An order is denominated in its own currency, so
     * ordering the column would rank 900 MYR above 500 USD and present the result as an
     * answer. The stored `exchange_rate` is what would make them comparable, and a sort on
     * `total * exchange_rate` is an expression this layer has no business writing. The same
     * argument {@see StockTakeData} makes about summing across units.
     *
     * The customer is absent for the reason {@see ProductController} gives about its
     * category: it lives on another table.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['number', 'status', 'expected_date', 'created_at'];

    public function index(Request $request): Response
    {
        // One status at a time, like the purchase orders list: an order is in exactly one of
        // three states and the control is a single select, so "any" is the empty string. An
        // unrecognised value is no filter and is not echoed back — `?status=nonsense` should
        // not sit in the URL looking as though it did something.
        $status = SalesOrderStatus::tryFrom($this->queryValue($request, 'status'));

        $customers = Customer::query()->orderBy('name')->get();
        $customer = $this->customerFilter($request, $customers);

        $query = SalesOrder::query()
            ->with(['customer', 'creator', 'fulfiller', 'fulfilledWarehouse'])
            // Counted by the database, in the same round trip as the page. v1 loaded every
            // line of every order into every list row to arrive at this one number.
            ->withCount('items as line_count');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($customer !== 0) {
            $query->where('customer_id', $customer);
        }

        ['rows' => $orders, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: SalesOrderData::fromSalesOrder(...),
            searchUsing: self::searchBy(...),
            extra: [
                'status' => $status === null ? '' : $status->value,
                'customer' => $customer === 0 ? '' : (string) $customer,
            ],
        );

        return Inertia::render('sales-orders/index', [
            'orders' => $orders,
            'filters' => $filters,
            'customers' => OptionData::collect($customers),
        ]);
    }

    /** A blank order form. Everything a person picks from travels with it. */
    public function create(): Response
    {
        return Inertia::render('sales-orders/form', [
            'order' => null,
            'items' => [],
            ...$this->formPickers(),
        ]);
    }

    /**
     * Take the order.
     *
     * Redirects to the order rather than returning `back()`: the reason to raise a sales
     * order is to have one, and leaving somebody on the list to find their own new row is a
     * step for nothing.
     */
    public function store(SalesOrderRequest $request, OpenSalesOrder $open): RedirectResponse
    {
        $order = $open->handle($request->orderHeader(), $request->lines(), self::signedInUser($request));

        $this->toast(__('sales-orders.toast.created'));

        return to_route('sales-orders.show', $order);
    }

    /**
     * The order as a document: its header, its priced lines, and — while it is still pending —
     * somewhere to ship it from and what that building holds.
     *
     * The warehouse picker is empty rather than absent once the order is closed, so the prop's
     * shape is one thing on the client instead of two. A fulfilled order has nothing left to
     * ship; where the goods went travels on the order itself.
     *
     * **`availability` is driven by `?warehouse_id`, not by `Inertia::optional()`**, and the
     * difference is the failed despatch. The screen refreshes the panel with a partial reload
     * — `only: ['availability']` — which is what an optional prop is for, and on that path the
     * two are identical. But a shortfall comes back as a `ValidationException`, which
     * redirects to this page and re-renders it in full, and a full render excludes an optional
     * prop by definition: the panel would go blank at the exact moment somebody needs to read
     * it. Keying off the query string instead means the partial reload leaves the answer in
     * the URL, and every later render of that URL — the redirect, a refresh, a shared link —
     * can work it out again.
     *
     * Null when no warehouse has been chosen, which is also what a first visit looks like. The
     * work is not done until somebody asks a question it answers.
     */
    public function show(Request $request, SalesOrder $salesOrder, StockService $stock): Response
    {
        $this->loadHeader($salesOrder);

        $lines = self::orderLines($salesOrder);

        $pending = $salesOrder->status === SalesOrderStatus::Pending;
        $warehouses = $pending ? $this->warehouseOptions() : [];
        $warehouse = $pending ? $this->chosenWarehouse($request, $warehouses) : null;

        return Inertia::render('sales-orders/show', [
            'order' => SalesOrderData::fromSalesOrder($salesOrder),
            'items' => self::lineData($salesOrder, $lines),
            'warehouses' => $warehouses,
            // The picker is seeded from this rather than starting empty. Without it a page
            // loaded *with* `?warehouse_id=2` — a refresh, a shared link, the redirect after a
            // refused despatch — draws a panel of figures about a warehouse the control above
            // it does not name, and leaves Fulfil disabled with no way to see why.
            'chosenWarehouse' => $warehouse === null ? '' : (string) $warehouse->id,
            'availability' => $warehouse === null
                ? null
                : self::availability($lines, $warehouse, $stock),
        ]);
    }

    /**
     * The same form, seeded from an order that has not shipped.
     *
     * Refused once the order is fulfilled or cancelled, and refused with a redirect to the
     * order rather than a 403: arriving here is what a bookmark or a stale tab does, and the
     * screen it lands on is the one that explains why.
     */
    public function edit(SalesOrder $salesOrder): Response|RedirectResponse
    {
        if ($salesOrder->status !== SalesOrderStatus::Pending) {
            $this->toast(__('sales-orders.error.not_pending'), 'error');

            return to_route('sales-orders.show', $salesOrder);
        }

        $this->loadHeader($salesOrder);

        return Inertia::render('sales-orders/form', [
            'order' => SalesOrderData::fromSalesOrder($salesOrder),
            'items' => self::lineData($salesOrder, self::orderLines($salesOrder)),
            ...$this->formPickers(),
        ]);
    }

    /**
     * Replace what was agreed.
     *
     * The whole order arrives, lines and all, and the action rewrites it — see
     * {@see OpenSalesOrder} on why that is a replacement rather than a diff. The status is
     * checked here because this is the ordinary case, and the number, the status and the
     * fulfilment columns are simply not among the fields an edit can name.
     */
    public function update(
        SalesOrderRequest $request,
        SalesOrder $salesOrder,
        OpenSalesOrder $open,
    ): RedirectResponse {
        if ($salesOrder->status !== SalesOrderStatus::Pending) {
            return $this->refuse(__('sales-orders.error.not_pending'));
        }

        $open->handle(
            $request->orderHeader(),
            $request->lines(),
            self::signedInUser($request),
            $salesOrder,
        );

        $this->toast(__('sales-orders.toast.updated'));

        return to_route('sales-orders.show', $salesOrder);
    }

    /**
     * Ship it: one movement per line out of the warehouse somebody names.
     *
     * The status is checked twice, and the two checks are not the same check. The one here
     * answers the ordinary case — a stale tab, a second press after a colleague — and deserves
     * a plain sentence. {@see FulfillSalesOrder} re-reads it under a lock, which is the only
     * place the true race can be settled, and a refusal from there arrives as a
     * {@see DomainException} and gets the same words.
     *
     * The warehouse is validated here rather than in a FormRequest of its own: it is one field
     * on a confirmation card, not a form anybody fills in — the same call
     * {@see PurchaseOrderController::receive()} makes, and a request class would make
     * `bun run check:validation` demand a zod schema for a single field. `integer` is doing
     * real work beside `exists`: without it `warehouse_id[]=7` validates and then applies to
     * row 1.
     *
     * **No zod gate anywhere in this module refuses a line for being short**, deliberately. A
     * sales order is a commitment to sell, routinely taken before the goods exist, and the
     * order carries no warehouse until this moment — so a save-time check would make a
     * backorder impossible to record. The shelf is only consulted here.
     */
    public function fulfill(
        Request $request,
        SalesOrder $salesOrder,
        FulfillSalesOrder $fulfill,
    ): RedirectResponse {
        if ($salesOrder->status !== SalesOrderStatus::Pending) {
            return $this->refuse(__('sales-orders.error.not_pending'));
        }

        $request->validate(['warehouse_id' => ['required', 'integer', ActiveExists::of('warehouses')]]);

        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        try {
            $fulfill->handle($salesOrder, $warehouse, self::signedInUser($request));
        } catch (InsufficientStockForOrderException $e) {
            // On `warehouse_id`, because that is the one control the card has — and because
            // changing it is the action that might make the message go away. The panel beside
            // it lists every short product; this names the shortfall a one-product order has,
            // and counts them when there is more than one.
            throw ValidationException::withMessages([
                'warehouse_id' => self::shortfallMessage($e->shortfalls),
            ]);
        } catch (InsufficientStockException) {
            // Unreachable: every level row is held under `FOR UPDATE` from before the check —
            // see the Action. Caught because the service declares it, and a lock path reasoned
            // about rather than proven should not surface as a 500. Nothing was written; the
            // transaction unwound.
            return $this->refuse(__('sales-orders.error.short_raced'));
        } catch (DomainException) {
            return $this->refuse(__('sales-orders.error.not_pending'));
        }

        $this->toast(__('sales-orders.toast.fulfilled'));

        return back();
    }

    /**
     * Call the order off. Nothing shipped, so nothing is unwound.
     *
     * Terminal, like fulfilling: a cancelled order is not reopened, it is superseded by
     * taking another — which leaves both on the record instead of quietly rewriting one.
     */
    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->status !== SalesOrderStatus::Pending) {
            return $this->refuse(__('sales-orders.error.not_pending'));
        }

        $salesOrder->forceFill(['status' => SalesOrderStatus::Cancelled])->save();

        $this->toast(__('sales-orders.toast.cancelled'));

        return back();
    }

    /**
     * Remove an order that never happened.
     *
     * **Only a pending one may go**, and that is the guard this method exists for. Every
     * ledger row a fulfilment writes will point back at the order as its source, so deleting
     * a fulfilled one would leave movements naming a document nobody can open — and a
     * cancelled order is a decision somebody made, which is exactly the kind of thing a record
     * is for. v1 allowed both, in any state.
     *
     * Soft, like every delete here. Redirects to the list rather than `back()`, because
     * `back()` from the order's own page is a page that no longer resolves.
     */
    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->status !== SalesOrderStatus::Pending) {
            return $this->refuse(__('sales-orders.error.fulfilled_locked'));
        }

        $salesOrder->delete();

        $this->toast(__('sales-orders.toast.deleted'));

        return to_route('sales-orders.index');
    }

    /**
     * Everything the order form picks from.
     *
     * `taxRate` is the workspace's **current** rate, not the one snapshotted on the order
     * being edited, and that is deliberate: the form's running total is a preview of what
     * saving will store, and saving re-snapshots the rate. Showing the old one would preview a
     * figure the save is not going to produce. The order's own rate still travels on
     * `order.tax_rate` for anything that wants to say what it was raised under.
     *
     * `products` is finished products only. A sales order sells what the workspace makes,
     * which is why this is not {@see BuildsStockPickers::itemOptions()} — that one offers raw
     * materials too, and selling your own inputs is not a thing this screen should offer.
     *
     * @return array<string, mixed>
     */
    private function formPickers(): array
    {
        $settings = BusinessSetting::current();

        return [
            'customers' => OptionData::collect(Customer::query()->orderBy('name')->get()),
            'products' => self::productOptions(),
            'currencies' => $settings->allowedCurrencies(),
            // A percentage — `'6'`, not `'6.0000'`, which is what the column returns and what
            // the browser's mirror would then render into every tax label.
            'taxRate' => Decimals::trim((string) $settings->tax_rate),
        ];
    }

    /**
     * The products picker, valued `product:5` — the same encoding {@see StockItem} uses
     * everywhere else, so one picker component serves this screen and the stock screens.
     *
     * The whole list rather than a search endpoint, the same trade the catalog pickers make: a
     * workspace has hundreds of products at most, and one query here is cheaper than a round
     * trip per keystroke there. Trashed rows are excluded by the model's own SoftDeletes
     * scope, which is what the request re-checks on the way back in.
     *
     * The price prefill comes free: {@see StockItemOptionData::fromModel()} reads
     * `default_cost ?? default_price`, and a product carries only the second.
     *
     * @return list<StockItemOptionData>
     */
    private static function productOptions(): array
    {
        $products = Product::query()->orderBy('name')->get()->map(StockItemOptionData::fromModel(...));

        return array_values($products->all());
    }

    /**
     * The lines, oldest first — the order they were entered in, which is the order the person
     * who entered them arranged.
     *
     * `product` is eager-loaded here rather than by each reader, because every reader needs it:
     * the line's own name comes off it, and so does the availability panel's. Loading it once
     * is also what lets {@see show()} answer both questions from a single query.
     *
     * @return Collection<int, SalesOrderItem>
     */
    private static function orderLines(SalesOrder $order): Collection
    {
        return $order->items()->with('product')->orderBy('id')->get();
    }

    /**
     * Those lines as the two screens read them.
     *
     * The currency travels with each line because a line has none of its own; see
     * {@see SalesOrderItemData} on what it is for.
     *
     * @param  Collection<int, SalesOrderItem>  $lines  from {@see orderLines()}
     * @return list<SalesOrderItemData>
     */
    private static function lineData(SalesOrder $order, Collection $lines): array
    {
        return array_values(
            $lines->map(
                static fn (SalesOrderItem $line): SalesOrderItemData => SalesOrderItemData::fromSalesOrderItem(
                    $line,
                    $order->currency,
                ),
            )->all(),
        );
    }

    /**
     * What one warehouse holds against what the order needs, one row per product.
     *
     * Through {@see OrderAvailability} rather than assembled here, and that is the whole point
     * of the class: {@see FulfillSalesOrder} decides the same question a second later and goes
     * through the same two calls, so the panel cannot show a row the Action then refuses for
     * arithmetic it did differently. Only the levels differ — unlocked here, because this is a
     * screen, and under `FOR UPDATE` there, because that is a guarantee.
     *
     * **Nothing about it is a reservation.** Two people can read the same eight and both go on
     * to ship five. That is why the number never disables the button; see the panel.
     *
     * @param  Collection<int, SalesOrderItem>  $lines
     * @return list<StockAvailabilityData>
     */
    private static function availability(Collection $lines, Warehouse $warehouse, StockService $stock): array
    {
        $required = OrderAvailability::required($lines);

        return OrderAvailability::rows(
            $required,
            $stock->onHandFor($warehouse, OrderAvailability::products($required)),
        );
    }

    /**
     * The warehouse the screen is asking about, or null for none.
     *
     * Checked against the list this page was given rather than against the table, the same
     * treatment the customer filter gets: a stale link naming a warehouse since closed asks a
     * question about a building that is no longer offered, and answering it would be worse
     * than not. A value that is not on the list is simply no choice at all.
     *
     * @param  list<WarehouseOptionData>  $warehouses
     */
    private function chosenWarehouse(Request $request, array $warehouses): ?Warehouse
    {
        $requested = (int) $this->queryValue($request, 'warehouse_id');

        $offered = array_map(
            static fn (WarehouseOptionData $warehouse): int => $warehouse->id,
            $warehouses,
        );

        return in_array($requested, $offered, true)
            ? Warehouse::query()->find($requested)
            : null;
    }

    /**
     * A shortfall, in this app's own voice and in the reader's language.
     *
     * **Built with `trans_choice`, never by joining names together.** `implode(', ', $names)`
     * would pick a separator on the server for a sentence it cannot see, in a language it had
     * to guess — and the word order around a list differs across en, ms and zh_Hans. So one
     * product gets a sentence naming it and its two numbers, and more than one gets a count
     * plus a pointer at the panel, which lists them in rows that need no separator at all.
     *
     * @param  non-empty-list<StockAvailabilityData>  $shortfalls
     */
    private static function shortfallMessage(array $shortfalls): string
    {
        $first = $shortfalls[0];

        return trans_choice('sales-orders.error.short', count($shortfalls), [
            'item' => $first->name,
            // Already trimmed by the DTO — `8` rather than `8.0000`, for the reason
            // {@see Decimals} gives. Quoted here so the two halves of the sentence agree
            // with the two numbers in the row it is about.
            'available' => $first->on_hand,
            'required' => $first->required,
        ]);
    }

    /**
     * The relations and the count every header needs — the detail page, the edit form, and the
     * row the two of them redirect back to. One definition, so a screen cannot quietly render
     * an order with a missing name on it.
     */
    private function loadHeader(SalesOrder $order): void
    {
        $order->load(['customer', 'creator', 'fulfiller', 'fulfilledWarehouse']);
        $order->loadCount('items as line_count');
    }

    /**
     * The customer filter, as an id the picker actually offered, or 0 for none.
     *
     * Checked against the list the screen was given rather than against the table, for the
     * reason {@see ProductController} gives about its material filter: a stale link naming a
     * customer since removed drops the filter instead of returning nothing, and "no results"
     * would read as "we have never sold to them" when the truth is that there is no such
     * customer.
     *
     * @param  Collection<int, Customer>  $customers
     */
    private function customerFilter(Request $request, Collection $customers): int
    {
        $requested = (int) $this->queryValue($request, 'customer');

        return in_array($requested, $customers->modelKeys(), true) ? $requested : 0;
    }

    /**
     * What searching orders means — see {@see SalesOrder::search()}, which covers the number,
     * the customer and the notes.
     *
     * @param  Builder<SalesOrder>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The signed-in person, or nobody.
     *
     * A console super-admin is a `CentralUser` row in another database, while `created_by` and
     * `fulfilled_by` point at this workspace's `users` table. Narrowing rather than casting:
     * naming them would be a foreign key into the wrong database, so the honest answer is that
     * nobody is named.
     */
    private static function signedInUser(Request $request): ?User
    {
        $signedIn = $request->user();

        return $signedIn instanceof User ? $signedIn : null;
    }

    /**
     * Refuse a lifecycle action in this app's own voice.
     *
     * A toast on the way back rather than a thrown 422: there is no field to underline — the
     * button was pressed against an order that has moved on — and the screen it returns to
     * already shows the state that explains why.
     */
    private function refuse(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return back();
    }
}
