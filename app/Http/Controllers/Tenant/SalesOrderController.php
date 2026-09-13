<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\OpenSalesOrder;
use App\Data\OptionData;
use App\Data\SalesOrderData;
use App\Data\SalesOrderItemData;
use App\Data\StockItemOptionData;
use App\Data\StockTakeData;
use App\Enums\SalesOrderStatus;
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
use App\Support\Decimals;
use App\Support\OrderTotals;
use App\Support\StockItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 * **Fulfilment is not here yet.** Issuing stock is the one genuinely new problem in this
 * module — it can fail because the goods are not there, which receiving never can — and it
 * ships as its own slice with its own lock, its own shortfall reporting and its own stock
 * verification. Until then an order can be raised, amended, read, cancelled and deleted,
 * which is a complete and useful document on its own.
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

    /** The order as a document: its header and its priced lines. */
    public function show(SalesOrder $salesOrder): Response
    {
        $this->loadHeader($salesOrder);

        return Inertia::render('sales-orders/show', [
            'order' => SalesOrderData::fromSalesOrder($salesOrder),
            'items' => self::lineData($salesOrder),
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
            'items' => self::lineData($salesOrder),
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
     * The currency travels with each line because a line has none of its own; see
     * {@see SalesOrderItemData} on what it is for.
     *
     * @return list<SalesOrderItemData>
     */
    private static function lineData(SalesOrder $order): array
    {
        $lines = $order->items()->with('product')->orderBy('id')->get();

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
