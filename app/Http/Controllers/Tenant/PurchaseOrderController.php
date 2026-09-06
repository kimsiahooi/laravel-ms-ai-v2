<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\OpenPurchaseOrder;
use App\Actions\ReceivePurchaseOrder;
use App\Data\OptionData;
use App\Data\PurchaseOrderData;
use App\Data\PurchaseOrderItemData;
use App\Data\StockItemOptionData;
use App\Data\StockTakeData;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Http\Requests\Tenant\TenantFormRequest;
use App\Models\BusinessSetting;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ActiveExists;
use App\Support\Decimals;
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
 * Goods ordered from a supplier: raised, corrected while pending, then received once.
 *
 * The first module in the app with a **form page rather than a dialog**, and the reason is
 * the lines: an order is a header and a grid of priced rows that a person works across,
 * which is not something a modal over a list can hold. So there is a create page, an edit
 * page and a detail page, where the catalog screens have one screen each.
 *
 * Nothing here computes money and nothing here writes stock. {@see OpenPurchaseOrder}
 * composes the document, its lines and the totals {@see OrderTotals} decides;
 * {@see ReceivePurchaseOrder} turns the order into ledger rows under a lock. This
 * controller resolves what the screen named, hands it over, and turns a refusal into a
 * message a person can read.
 *
 * **A lifecycle refusal is branded feedback, never a bare 422.** Receiving an order a
 * colleague already received, or editing one that has shipped, is an ordinary thing to
 * arrive at from a stale tab — so each one leaves as an error toast or a validation
 * message. v1 used `abort_unless($order->status === Pending, 422)` in four places, which
 * surfaces as Inertia's raw error modal: the wrong register for "somebody beat you to it",
 * and untranslatable besides.
 */
final class PurchaseOrderController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by. This list is the SQL-injection guard for
     * `?sort=` — see {@see SortsResourceQuery} — and it decides which headers the table
     * renders as clickable.
     *
     * **`total` is deliberately absent.** An order is denominated in its own currency, so
     * ordering the column would rank 900 MYR above 500 USD and present the result as an
     * answer. The stored `exchange_rate` is what would make them comparable, and a sort on
     * `total * exchange_rate` is an expression this layer has no business writing. The
     * same argument {@see StockTakeData} makes about summing across units.
     *
     * The supplier is absent for the reason {@see ProductController} gives about its
     * category: it lives on another table.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['number', 'status', 'expected_date', 'created_at'];

    public function index(Request $request): Response
    {
        // One status at a time, like the stock takes list: an order is in exactly one of
        // three states and the control is a single select, so "any" is the empty string.
        // An unrecognised value is no filter and is not echoed back — `?status=nonsense`
        // should not sit in the URL looking as though it did something.
        $status = PurchaseOrderStatus::tryFrom($this->queryValue($request, 'status'));

        $suppliers = Supplier::query()->orderBy('name')->get();
        $supplier = $this->supplierFilter($request, $suppliers);

        $query = PurchaseOrder::query()
            ->with(['supplier', 'creator', 'receiver', 'receivedWarehouse'])
            // Counted by the database, in the same round trip as the page. v1 loaded every
            // line of every order into every list row to arrive at this one number.
            ->withCount('items as line_count');

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($supplier !== 0) {
            $query->where('supplier_id', $supplier);
        }

        ['rows' => $orders, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: PurchaseOrderData::fromPurchaseOrder(...),
            searchUsing: self::searchBy(...),
            extra: [
                'status' => $status === null ? '' : $status->value,
                'supplier' => $supplier === 0 ? '' : (string) $supplier,
            ],
        );

        return Inertia::render('purchase-orders/index', [
            'orders' => $orders,
            'filters' => $filters,
            'suppliers' => OptionData::collect($suppliers),
        ]);
    }

    /** A blank order form. Everything a person picks from travels with it. */
    public function create(): Response
    {
        return Inertia::render('purchase-orders/form', [
            'order' => null,
            'items' => [],
            ...$this->formPickers(),
        ]);
    }

    /**
     * Raise the order.
     *
     * Redirects to the order rather than returning `back()`, which the catalog screens do:
     * the reason to raise a purchase order is to have one, and leaving somebody on the
     * list to find their own new row is a step for nothing. The same call
     * {@see StockTakeController::store()} makes.
     */
    public function store(PurchaseOrderRequest $request, OpenPurchaseOrder $open): RedirectResponse
    {
        $order = $open->handle($request->orderHeader(), $request->lines(), self::signedInUser($request));

        $this->toast(__('purchase-orders.toast.created'));

        return to_route('purchase-orders.show', $order);
    }

    /**
     * The order as a document: its header, its priced lines, and — while it is still
     * pending — somewhere to receive it into.
     *
     * The warehouse picker is empty rather than absent once the order is closed, so the
     * prop's shape is one thing on the client instead of two. A received order has nowhere
     * left to put anything, and sending the list with it would be pure weight; where the
     * goods actually went travels on the order itself.
     */
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->loadHeader($purchaseOrder);

        return Inertia::render('purchase-orders/show', [
            'order' => PurchaseOrderData::fromPurchaseOrder($purchaseOrder),
            'items' => self::lineData($purchaseOrder),
            'warehouses' => $purchaseOrder->status === PurchaseOrderStatus::Pending
                ? $this->warehouseOptions()
                : [],
        ]);
    }

    /**
     * The same form, seeded from an order that has not shipped.
     *
     * Refused once the order is received or cancelled, and refused with a redirect to the
     * order rather than a 403: arriving here is what a bookmark or a stale tab does, and
     * the screen it lands on is the one that explains why.
     */
    public function edit(PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            $this->toast(__('purchase-orders.error.not_pending'), 'error');

            return to_route('purchase-orders.show', $purchaseOrder);
        }

        $this->loadHeader($purchaseOrder);

        return Inertia::render('purchase-orders/form', [
            'order' => PurchaseOrderData::fromPurchaseOrder($purchaseOrder),
            'items' => self::lineData($purchaseOrder),
            ...$this->formPickers(),
        ]);
    }

    /**
     * Replace what was agreed.
     *
     * The whole order arrives, lines and all, and the action rewrites it — see
     * {@see OpenPurchaseOrder} on why that is a replacement rather than a diff. The status
     * is checked here because this is the ordinary case, and the number, the status and
     * the receipt columns are simply not among the fields an edit can name.
     */
    public function update(
        PurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        OpenPurchaseOrder $open,
    ): RedirectResponse {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return $this->refuse(__('purchase-orders.error.not_pending'));
        }

        $open->handle(
            $request->orderHeader(),
            $request->lines(),
            self::signedInUser($request),
            $purchaseOrder,
        );

        $this->toast(__('purchase-orders.toast.updated'));

        return to_route('purchase-orders.show', $purchaseOrder);
    }

    /**
     * Book the goods in: one movement per line into the warehouse somebody names.
     *
     * The status is checked twice, and the two checks are not the same check. The one here
     * answers the ordinary case — a stale tab, a second press after a colleague — and
     * deserves a plain sentence. {@see ReceivePurchaseOrder} re-reads it under a lock,
     * which is the only place the true race can be settled, and a refusal from there
     * arrives as a {@see DomainException} and gets the same words.
     *
     * The warehouse is validated here rather than in a FormRequest of its own: it is one
     * field on a confirmation dialog, not a form anybody fills in. `integer` is doing real
     * work beside `exists` — see {@see TenantFormRequest} on why
     * `warehouse_id[]=7` otherwise validates and then applies to row 1.
     */
    public function receive(
        Request $request,
        PurchaseOrder $purchaseOrder,
        ReceivePurchaseOrder $receive,
    ): RedirectResponse {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return $this->refuse(__('purchase-orders.error.not_pending'));
        }

        $request->validate(['warehouse_id' => ['required', 'integer', ActiveExists::of('warehouses')]]);

        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        try {
            $receive->handle($purchaseOrder, $warehouse, self::signedInUser($request));
        } catch (InsufficientStockException $e) {
            // Unreachable for a receipt, which only ever adds — see the action. Caught
            // because the service declares it, and a 500 is the wrong answer to a number
            // somebody has to look at again. On the warehouse field, because that is the
            // one control the dialog has.
            throw ValidationException::withMessages([
                'warehouse_id' => __('purchase-orders.error.insufficient', [
                    'available' => Decimals::trim($e->available),
                    'requested' => Decimals::trim($e->requested),
                ]),
            ]);
        } catch (DomainException) {
            return $this->refuse(__('purchase-orders.error.not_pending'));
        }

        $this->toast(__('purchase-orders.toast.received'));

        return back();
    }

    /**
     * Call the order off. Nothing was received, so nothing is unwound.
     *
     * Terminal, like receiving: a cancelled order is not reopened, it is superseded by
     * raising another — which leaves both on the record instead of quietly rewriting one.
     */
    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return $this->refuse(__('purchase-orders.error.not_pending'));
        }

        $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

        $this->toast(__('purchase-orders.toast.cancelled'));

        return back();
    }

    /**
     * Remove an order that never happened.
     *
     * **Only a pending one may go**, and that is the guard this method exists for. Every
     * ledger row a receipt wrote points back at the order as its source, so deleting a
     * received one would leave movements naming a document nobody can open — and a
     * cancelled order is a decision somebody made, which is exactly the kind of thing a
     * record is for. v1 allowed both.
     *
     * Soft, like every delete here. Redirects to the list rather than `back()`, because
     * `back()` from the order's own page is a page that no longer resolves.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Pending) {
            return $this->refuse(__('purchase-orders.error.received_locked'));
        }

        $purchaseOrder->delete();

        $this->toast(__('purchase-orders.toast.deleted'));

        return to_route('purchase-orders.index');
    }

    /**
     * Everything the order form picks from.
     *
     * `taxRate` is the workspace's **current** rate, not the one snapshotted on the order
     * being edited, and that is deliberate: the form's running total is a preview of what
     * saving will store, and saving re-snapshots the rate. Showing the old one would
     * preview a figure the save is not going to produce. The order's own rate still
     * travels on `order.tax_rate` for anything that wants to say what it was raised under.
     *
     * `materials` is raw materials only. A purchase order buys what the workspace
     * consumes, which is why this is not {@see BuildsStockPickers::itemOptions()} — that
     * one offers finished products too.
     *
     * @return array<string, mixed>
     */
    private function formPickers(): array
    {
        $settings = BusinessSetting::current();

        return [
            'suppliers' => OptionData::collect(Supplier::query()->orderBy('name')->get()),
            'materials' => self::materialOptions(),
            'currencies' => $settings->allowedCurrencies(),
            // A percentage — `'6'`, not `'6.0000'`, which is what the column returns and
            // what the browser's mirror would then render into every tax label.
            'taxRate' => Decimals::trim((string) $settings->tax_rate),
        ];
    }

    /**
     * The materials picker, valued `raw_material:5` — the same encoding
     * {@see StockItem} uses everywhere else, so one picker component serves
     * this screen and the stock screens.
     *
     * The whole list rather than a search endpoint, the same trade the catalog pickers
     * make: a workspace has hundreds of materials at most, and one query here is cheaper
     * than a round trip per keystroke there. Trashed rows are excluded by the model's own
     * SoftDeletes scope, which is what the request re-checks on the way back in.
     *
     * @return list<StockItemOptionData>
     */
    private static function materialOptions(): array
    {
        $materials = RawMaterial::query()->orderBy('name')->get()->map(StockItemOptionData::fromModel(...));

        return array_values($materials->all());
    }

    /**
     * The lines, oldest first — the order they were entered in, which is the order the
     * person who entered them arranged.
     *
     * The currency travels with each line because a line has none of its own; see
     * {@see PurchaseOrderItemData} on what it is for.
     *
     * @return list<PurchaseOrderItemData>
     */
    private static function lineData(PurchaseOrder $order): array
    {
        $lines = $order->items()->with('rawMaterial')->orderBy('id')->get();

        return array_values(
            $lines->map(
                static fn (PurchaseOrderItem $line): PurchaseOrderItemData => PurchaseOrderItemData::fromPurchaseOrderItem(
                    $line,
                    $order->currency,
                ),
            )->all(),
        );
    }

    /**
     * The relations and the count every header needs — the detail page, the edit form,
     * and the row the two of them redirect back to. One definition, so a screen cannot
     * quietly render an order with a missing name on it.
     */
    private function loadHeader(PurchaseOrder $order): void
    {
        $order->load(['supplier', 'creator', 'receiver', 'receivedWarehouse']);
        $order->loadCount('items as line_count');
    }

    /**
     * The supplier filter, as an id the picker actually offered, or 0 for none.
     *
     * Checked against the list the screen was given rather than against the table, for the
     * reason {@see ProductController} gives about its material filter: a stale link naming
     * a supplier since removed drops the filter instead of returning nothing, and "no
     * results" would read as "we buy nothing from them" when the truth is that there is no
     * such supplier.
     *
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function supplierFilter(Request $request, Collection $suppliers): int
    {
        $requested = (int) $this->queryValue($request, 'supplier');

        return in_array($requested, $suppliers->modelKeys(), true) ? $requested : 0;
    }

    /**
     * What searching orders means — see {@see PurchaseOrder::search()}, which covers the
     * number, the supplier and the notes.
     *
     * @param  Builder<PurchaseOrder>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The signed-in person, or nobody.
     *
     * A console super-admin is a `CentralUser` row in another database, while `created_by`
     * and `received_by` point at this workspace's `users` table. Narrowing rather than
     * casting: naming them would be a foreign key into the wrong database, so the honest
     * answer is that nobody is named.
     */
    private static function signedInUser(Request $request): ?User
    {
        $signedIn = $request->user();

        return $signedIn instanceof User ? $signedIn : null;
    }

    /**
     * Refuse a lifecycle action in this app's own voice.
     *
     * A toast on the way back rather than a thrown 422: there is no field to underline —
     * the button was pressed against an order that has moved on — and the screen it
     * returns to already shows the state that explains why.
     */
    private function refuse(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return back();
    }
}
