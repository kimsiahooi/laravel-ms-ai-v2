<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StockMovementReason;
use App\Enums\StockTakeStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\StockItem;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a finished count sheet into stock, as one adjustment.
 *
 * **Nothing here subtracts.** Every line is handed to {@see StockService::setLevel()} as
 * a target, and the service works out the difference from the on-hand it reads under its
 * own lock. v1 computed `counted - onHand()` here, from an unlocked read taken while the
 * sheet was being filled in — so a sale that shipped between the read and the write was
 * undone by the count, silently and with a ledger row claiming the count had found it.
 * Handing over the target instead means the only number that becomes a movement is one
 * derived under the lock that guards the row.
 *
 * That is also why `applied_delta` is written from what came back rather than from
 * `counted - system_quantity`: the snapshot on the line is what the counter was asked to
 * confirm, and the sheet may have been open for an hour. The number stored is the number
 * that moved.
 *
 * **An uncounted line is skipped, because it is not a claim about anything.** Null in
 * `counted_quantity` means nobody has reached that shelf yet. v1 seeded every line with
 * the system quantity, which made "not counted" indistinguishable from "counted, and it
 * matched" — so posting a half-finished sheet rolled every untouched item back to
 * whatever the snapshot said, undoing real movements nobody had looked at.
 *
 * **The outer transaction is what makes this one adjustment rather than several.**
 * `setLevel()` opens its own, which Laravel turns into a savepoint, so a refusal on line
 * 400 — the only one this can raise — takes the 399 movements before it with it. A
 * partly posted count is worse than an unposted one: the sheet would say it had been
 * applied and half the warehouse would disagree.
 */
final class PostStockTake
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @throws InsufficientStockException declared because {@see StockService::setLevel()}
     *                                    declares it, and unreachable while counts are
     *                                    validated `gte:0` — a target that is not
     *                                    negative cannot leave on-hand negative. The
     *                                    controller still catches it and puts it on the
     *                                    field, because the day that rule is relaxed is
     *                                    not the day to discover this was a 500.
     * @throws DomainException when the take stopped being a draft between the
     *                         controller's check and this lock — the true
     *                         double-press race. Reported rather than
     *                         swallowed: the second press must not claim to
     *                         have posted a count the first press posted.
     */
    public function handle(StockTake $take, ?User $user = null): StockTake
    {
        return DB::transaction(function () use ($take, $user): StockTake {
            // Re-read under `FOR UPDATE`, inside the transaction. v1 checked the status
            // on the model the route had already bound, outside any transaction, so two
            // people pressing Post at the same moment both saw a draft and both posted —
            // every difference applied twice. The second one now blocks here and finds
            // the status the first one wrote.
            $locked = StockTake::query()->whereKey($take->getKey())->lockForUpdate()->first();

            // Already posted, already cancelled, or deleted from under us. The ordinary
            // press on a non-draft take never reaches here — the controller refuses it
            // first, with a sentence a person can read. Arriving here means the status
            // changed *after* that check, which is the true double-press race, and the
            // caller has to be told: returning the take quietly would let the second
            // press report "posted and stock updated" for work it did not do.
            if ($locked === null || $locked->status !== StockTakeStatus::Draft) {
                throw new DomainException('Stock take is no longer a draft.');
            }

            // `stockable` eager-loaded withTrashed by the relation itself, so a line
            // whose item was archived mid-count still says what it was counting.
            $lines = $locked->items()->with('stockable')->get();

            foreach ($lines as $line) {
                $this->apply($locked, $line, $user);
            }

            $locked->forceFill([
                'status' => StockTakeStatus::Posted,
                'posted_at' => now(),
                // Not the creator: the two are separate columns precisely so that both
                // are knowable — see the migration.
                'posted_by' => $user?->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * One line: set the level it counted, and record what that actually moved.
     *
     * @throws InsufficientStockException
     */
    private function apply(StockTake $take, StockTakeItem $line, ?User $user): void
    {
        if ($line->counted_quantity === null) {
            return;
        }

        $stockable = $line->stockable;

        // The two things a workspace holds stock of, named rather than tested as a bare
        // Model — the same pair {@see StockItem::decode()} promises. Null means the
        // catalogue row was force-deleted while the sheet was open; trashed means it was
        // archived. Either way there is no longer a level to set, and a count of
        // something that no longer exists is not worth failing the whole post over.
        if (! $stockable instanceof Product && ! $stockable instanceof RawMaterial) {
            return;
        }

        if ($stockable->trashed()) {
            return;
        }

        $movement = $this->stock->setLevel(
            $take->warehouse,
            $stockable,
            (string) $line->counted_quantity,
            StockMovementReason::StockTake,
            $user,
            __('stock-takes.movement.notes', ['id' => $take->id]),
        );

        // Null came back because the count agreed with the on-hand and the service
        // declined to append a ledger row saying nothing moved. The line still gets a
        // number: `0` is what was applied, and leaving it null would put a counted line
        // back among the ones nobody reached. Spelled out as a branch rather than a `??`
        // default, because null here is a specific outcome worth naming, not an absence.
        $line->forceFill([
            'applied_delta' => $movement === null ? '0' : $movement->quantity,
        ])->save();
    }
}
