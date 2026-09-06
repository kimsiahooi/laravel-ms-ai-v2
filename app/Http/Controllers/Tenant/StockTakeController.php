<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\OpenStockTake;
use App\Actions\PostStockTake;
use App\Data\StockTakeData;
use App\Data\StockTakeItemData;
use App\Enums\StockTakeStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\BuildsStockPickers;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\StockTakeCountRequest;
use App\Http\Requests\Tenant\StockTakeLineRequest;
use App\Http\Requests\Tenant\StockTakeRequest;
use App\Models\StockTake;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Support\Decimals;
use App\Support\StockItem;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A physical count of one warehouse: staged as a draft, posted once as an adjustment.
 *
 * The odd one out among the stock screens. Movements and transfers are records of
 * something that already happened, so they are written once and never edited; a stock
 * take is a **document somebody is still filling in**, which is why this controller has
 * a show, two writes that touch a draft, and three that end its life.
 *
 * **Counts are saved as they are entered.** v1 held the whole sheet in React state and
 * posted it in one go, so a refresh, a dropped connection or a closed tab during a
 * four-hour count lost every number. {@see self::count()} persists one line at a time and is
 * the only write in the app that deliberately says nothing when it succeeds.
 *
 * Nothing here computes stock, and nothing here reconciles anything. Posting is
 * {@see PostStockTake}, which re-reads each line's on-hand under a lock and lets
 * {@see StockService} work out what moved — the point being that the difference is
 * decided at the moment it is applied, not at the moment the sheet was drawn. This
 * controller resolves what the screen named, hands it over, and turns a refusal into a
 * message on the right field.
 *
 * **A lifecycle refusal is branded feedback, never a bare 422.** Posting a take that
 * somebody else already posted, or deleting one that has been applied, is an ordinary
 * thing to arrive at from a stale page — so each one leaves as a validation message or
 * an error toast. v1's `abort(422, 'Not a draft')` surfaced as Inertia's raw error
 * modal, which is the wrong register for "somebody beat you to it".
 */
final class StockTakeController
{
    use BuildsStockPickers;
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Newest first, because a count is dated work and the one you opened this morning
     * is the one you want. `status` is here as well: "show me what is still open" is
     * the second question anybody asks this list, and sorting answers it even when the
     * filter beside it is set to anything.
     *
     * Neither progress nor differences are sortable — they are counted per row by the
     * subqueries in {@see progressCounts()}, and ordering by one would mean naming an
     * aggregate that the sort whitelist cannot vouch for.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['status', 'created_at'];

    public function index(Request $request): Response
    {
        // One status at a time, unlike the ledger's reason filter: a take is in exactly
        // one of three states and the control is a single select, so "any" is the empty
        // string rather than every value ticked. An unrecognised value is no filter and
        // is not echoed back — `?status=nonsense` should not sit in the URL looking as
        // though it did something.
        $status = StockTakeStatus::tryFrom($this->queryValue($request, 'status'));

        $query = StockTake::query()
            ->with(['warehouse.location', 'creator', 'poster'])
            ->withCount(self::progressCounts());

        if ($status !== null) {
            $query->where('status', $status);
        }

        ['rows' => $takes, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: StockTakeData::fromStockTake(...),
            searchUsing: self::searchBy(...),
            extra: ['status' => $status === null ? '' : $status->value],
        );

        return Inertia::render('stock-takes/index', [
            'takes' => $takes,
            'filters' => $filters,
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    /**
     * Open a count sheet for one warehouse.
     *
     * The draft and its snapshot are one act — a take with no lines is a sheet nobody
     * can count on — so both belong to {@see OpenStockTake} rather than here.
     *
     * Redirects to the sheet instead of returning `back()`, which every other create in
     * this app does: the reason to start a count is to start counting, and leaving
     * somebody on the list to find their own new row is a step for nothing.
     */
    public function store(StockTakeRequest $request, OpenStockTake $open): RedirectResponse
    {
        $warehouse = Warehouse::query()->findOrFail($request->integer('warehouse_id'));

        $take = $open->handle($warehouse, self::signedInUser($request), $request->input('notes'));

        $this->toast(__('stock-takes.toast.opened'));

        return to_route('stock-takes.show', $take);
    }

    /**
     * The count sheet itself.
     *
     * Lines are ordered by id, which is the order they joined the sheet: the snapshot
     * first, then anything found on the shelf appended underneath. Not by name — the
     * names live on two other catalogue tables, and ordering across a morph is a query
     * this layer has no business writing. Insertion order also puts a just-added item
     * where the person who added it is already looking.
     */
    public function show(Request $request, StockTake $stockTake): Response
    {
        $stockTake->load(['warehouse.location', 'creator', 'poster']);
        $stockTake->loadCount(self::progressCounts());

        $lines = $stockTake->items()->with('stockable')->orderBy('id')->get();

        return Inertia::render('stock-takes/show', [
            'take' => StockTakeData::fromStockTake($stockTake),
            'items' => array_values($lines->map(StockTakeItemData::fromStockTakeItem(...))->all()),
            // The picker behind "add an item found on the shelf". Empty rather than
            // absent once the take is closed, so the prop's shape is one thing on the
            // client instead of two — and a posted sheet has nothing to add anyway,
            // which makes sending the whole catalogue with it pure weight.
            'items_available' => $stockTake->status === StockTakeStatus::Draft ? $this->itemOptions() : [],
        ]);
    }

    /**
     * Save one counted number.
     *
     * **No toast, deliberately.** This fires every time somebody leaves a field, and a
     * notification per line is a wall of them over a hundred-line sheet — the row's own
     * saved indicator is the receipt. The same silence, for the same reason, that the
     * reorder-levels controller keeps.
     *
     * A non-numeric value clears the line back to uncounted, which is a different claim
     * from a counted zero and is stored as a different thing: null means nobody has been
     * to that shelf, `0` means somebody went and found it bare. The validator has already
     * refused everything that is neither a decimal nor empty, so `is_numeric` here is
     * what turns a checked value into a string the column will take.
     */
    public function count(StockTakeCountRequest $request, StockTake $stockTake): RedirectResponse
    {
        if ($stockTake->status !== StockTakeStatus::Draft) {
            // On the field the sheet is editing, so the row that refused says so itself.
            throw ValidationException::withMessages([
                'counted_quantity' => __('stock-takes.error.not_draft'),
            ]);
        }

        // Through the relation, not by id alone: the request's rule already scoped the
        // line to this take, and going back through `items()` is what keeps that true
        // even if the rule is ever loosened.
        $line = $stockTake->items()->findOrFail($request->integer('line'));

        $counted = $request->input('counted_quantity');

        $line->forceFill([
            'counted_quantity' => is_numeric($counted) ? (string) $counted : null,
        ])->save();

        return back();
    }

    /**
     * Add an item found on the shelf that this warehouse has no stock row for.
     *
     * The whole reason the sheet is open rather than fixed: v1 could only count what the
     * system already believed was there, so anything genuinely misplaced could be seen
     * and not recorded.
     *
     * `system_quantity` comes from the unlocked {@see StockService::onHand()}, which is
     * correct precisely because the number is never used for arithmetic — it is what the
     * counter is asked to confirm. Posting re-reads it under a lock.
     */
    public function addLine(StockTakeLineRequest $request, StockTake $stockTake, StockService $stock): RedirectResponse
    {
        $item = StockItem::decode((string) $request->string('item'));

        if ($item === null) {
            // Unreachable — the request rule just resolved the same value. Kept because
            // a null here would otherwise surface as a TypeError inside the service.
            throw ValidationException::withMessages([
                'item' => __('validation.exists', ['attribute' => __('validation.attributes.item')]),
            ]);
        }

        if ($stockTake->status !== StockTakeStatus::Draft) {
            throw ValidationException::withMessages(['item' => __('stock-takes.error.not_draft')]);
        }

        $duplicate = $stockTake->items()
            ->where('stockable_type', $item->getMorphClass())
            ->where('stockable_id', $item->getKey())
            ->exists();

        if ($duplicate) {
            // Checked rather than left to the unique index: the collision is somebody
            // picking something already on the sheet, which is a message on the picker,
            // not a 500. The index stays as the guard against two of these at once.
            throw ValidationException::withMessages(['item' => __('stock-takes.error.duplicate_item')]);
        }

        // The relation names `stock_take_id`; every other column is named here. Stock
        // tables declare no `$fillable`, so a write has to say exactly what it sets.
        $stockTake->items()->forceCreate([
            'stockable_type' => $item->getMorphClass(),
            'stockable_id' => $item->getKey(),
            'system_quantity' => $stock->onHand($stockTake->warehouse, $item),
            'counted_quantity' => null,
        ]);

        $this->toast(__('stock-takes.toast.item_added'));

        return back();
    }

    /**
     * Apply the count: on-hand becomes what was counted, and the difference goes to the
     * ledger. All of it or none of it — see {@see PostStockTake}.
     *
     * The status is checked twice, and the two checks are not the same check. The one
     * here answers the ordinary case: somebody left the sheet open in a tab and pressed
     * Post after a colleague already did, which deserves a plain sentence rather than an
     * exception. The action re-reads it under a lock, which is the only place the true
     * race can be settled; a refusal from there arrives as a {@see DomainException} and
     * gets the same words.
     */
    public function post(Request $request, StockTake $stockTake, PostStockTake $postTake): RedirectResponse
    {
        if ($stockTake->status !== StockTakeStatus::Draft) {
            return $this->refuse(__('stock-takes.error.not_draft'));
        }

        try {
            $postTake->handle($stockTake, self::signedInUser($request));
        } catch (InsufficientStockException $e) {
            // On the count, not as a toast: a counted number that would drive a level
            // below zero is a number somebody has to look at again, and the two figures
            // are what makes it actionable. Trimmed because a person is about to read
            // them — "Only 40.5 available" beats "Only 40.5000".
            throw ValidationException::withMessages([
                'counted_quantity' => __('stock-takes.error.insufficient', [
                    'available' => Decimals::trim($e->available),
                    'requested' => Decimals::trim($e->requested),
                ]),
            ]);
        } catch (DomainException) {
            return $this->refuse(__('stock-takes.error.not_draft'));
        }

        $this->toast(__('stock-takes.toast.posted'));

        return back();
    }

    /**
     * Abandon a draft. Stock is left exactly as it is.
     *
     * Terminal, like posting: a cancelled take is not reopened, it is superseded by
     * counting again — which leaves both attempts on the record instead of quietly
     * rewriting one.
     */
    public function cancel(Request $request, StockTake $stockTake): RedirectResponse
    {
        if ($stockTake->status !== StockTakeStatus::Draft) {
            return $this->refuse(__('stock-takes.error.not_draft'));
        }

        $stockTake->forceFill(['status' => StockTakeStatus::Cancelled])->save();

        $this->toast(__('stock-takes.toast.cancelled'));

        return back();
    }

    /**
     * Remove a take that never changed anything.
     *
     * **A posted take cannot be deleted**, and that is the one guard this method exists
     * for. Every ledger row the count wrote carries a note pointing back at it, so
     * deleting the sheet leaves the movements naming a document nobody can open. v1
     * allowed it. A draft or a cancelled take moved no stock, so both may go.
     *
     * Soft, like every delete here: the row leaves the list and stays answerable.
     */
    public function destroy(Request $request, StockTake $stockTake): RedirectResponse
    {
        if ($stockTake->status === StockTakeStatus::Posted) {
            return $this->refuse(__('stock-takes.error.posted_locked'));
        }

        $stockTake->delete();

        $this->toast(__('stock-takes.toast.deleted'));

        return back();
    }

    /**
     * The three numbers a take is read by: how many lines it has, how many have been
     * counted, and how many of those disagree with what the system expected.
     *
     * Counted by the database, in one round trip with the page. v1 serialised every line
     * into every list row and hydrated up to 5000 models per row to arrive at the same
     * figures — a list of ten takes was fifty thousand models.
     *
     * One definition shared by the list and the header, because a progress figure that
     * differed between two screens showing the same take is a bug nobody would think to
     * go looking for.
     *
     * A **count** of differing lines, never a sum of the differences: see
     * {@see StockTakeData} on why a signed total across mixed units is a lie. And an
     * uncounted line is not a difference — it is a shelf nobody has been to yet, which
     * is why both of the last two subqueries start by excluding them.
     *
     * @return array<int|string, string|Closure>
     */
    private static function progressCounts(): array
    {
        return [
            'items as line_count',
            'items as counted_count' => static function (Builder $query): void {
                $query->whereNotNull('counted_quantity');
            },
            'items as variance_count' => static function (Builder $query): void {
                $query
                    ->whereNotNull('counted_quantity')
                    ->whereColumn('counted_quantity', '!=', 'system_quantity');
            },
        ];
    }

    /**
     * What searching stock takes means — see {@see StockTake::search()}, which covers
     * the warehouse, its site, and the notes.
     *
     * @param  Builder<StockTake>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }

    /**
     * The signed-in person, or nobody.
     *
     * A console super-admin is a `CentralUser` row in another database, while
     * `created_by` and `posted_by` point at this workspace's `users` table. Narrowing
     * rather than casting: naming them would be a foreign key into the wrong database,
     * so the honest answer is that nobody is named.
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
     * the button was pressed against a take that has moved on — and the screen it
     * returns to already shows the state that explains why.
     */
    private function refuse(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return back();
    }
}
