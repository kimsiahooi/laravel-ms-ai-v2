<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Tenant\SalesOrderController;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use App\Support\ReturnedQuantities;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Raises a credit note against a despatch, or rewrites a pending one.
 *
 * The mirror of {@see SavePurchaseReturn}, and every argument it makes holds here.
 *
 * **The money is copied, never accepted.** Every line's unit price, discount and taxable flag
 * come off the order line it names, and the header's currency, rate and tax rate come off the
 * order. A return credits a charge that has already been made: computed at today's settings it
 * would credit a different figure from the one the customer was invoiced, and the two documents
 * would never reconcile.
 *
 * **One transaction around all of it**: {@see DocumentNumberGenerator::next()} refuses to run
 * outside one and its row lock is released at commit, so a separately-committed header hands the
 * same number to somebody else; the lines are deleted and rewritten, so a failure between the
 * two would leave a priced return with nothing on it; and the totals derive from the lines.
 *
 * **No lock on the ceiling, and that is a decision rather than an omission.** Two people can
 * raise two pending returns that together exceed a line — the FormRequest's ceiling reads without
 * one, so both pass. Nothing has moved: a pending return is a claim, an editor can correct it,
 * and {@see CompleteSalesReturn} re-reads the ceiling under the parent order's lock at the step
 * that is irreversible. Serialising document *creation* for a race with no consequence would be
 * the wrong trade, and it is the same call {@see SalesOrderController} makes about
 * over-committing stock when an order is taken.
 *
 * **There is a lock on the document being rewritten, though.** An edit that passes the
 * controller's status check on a stale tab and then blocks behind a completion would rewrite the
 * lines of a return whose goods are already back on a shelf — and {@see revise()}'s
 * `forceFill(...)->save()` is a no-op when nothing is dirty, so the header row might never be
 * locked at all before `$return->items()->delete()` runs. So the revise path re-reads the return
 * under `lockForUpdate` and refuses anything but a pending one.
 *
 * What is enforceable about the quantities lives in {@see ReturnedQuantities}.
 */
final class SaveSalesReturn
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * @param  array{sales_order_id: int, reason: ReturnReason, notes: string|null}  $fields
     * @param  list<array{sales_order_item_id: int, quantity: string}>  $lines
     * @param  SalesReturn|null  $return  the return being rewritten, or null to raise one
     *
     * @throws DomainException when the return stopped being pending between the controller's
     *                         check and this lock — see the class note.
     */
    public function handle(array $fields, array $lines, ?User $user = null, ?SalesReturn $return = null): SalesReturn
    {
        return DB::transaction(function () use ($fields, $lines, $user, $return): SalesReturn {
            // Before anything is read off it, and before a single line is deleted.
            $locked = $return === null ? null : self::lockPending($return);

            // On an edit the parent comes off the return itself, never off the payload — the
            // request pins the field for the same reason, and between them re-parenting is not
            // a thing that can be expressed.
            $order = $locked === null
                ? SalesOrder::query()->findOrFail($fields['sales_order_id'])
                : $locked->salesOrder;

            $priced = self::price($lines, $order);

            $totals = OrderTotals::forOrder(
                self::moneyLines($priced),
                $order->tax_rate,
                $order->currency,
            );

            $saved = $locked === null
                ? $this->open($order, $fields, $totals, $user)
                : $this->revise($locked, $order, $fields, $totals);

            $this->writeLines($saved, $priced);

            return $saved;
        });
    }

    /**
     * The return as it stands right now, held for the rest of the transaction.
     *
     * Re-read rather than trusted: what the caller holds is the instance the route bound, whose
     * status was true when the page was rendered.
     *
     * `null` is a real answer — the model soft deletes — and it gets the same refusal as a
     * completed one, because from the editor's side both mean "this is no longer yours to
     * change".
     *
     * @throws DomainException
     */
    private static function lockPending(SalesReturn $return): SalesReturn
    {
        $locked = SalesReturn::query()->whereKey($return->getKey())->lockForUpdate()->first();

        if ($locked === null || $locked->status !== ReturnStatus::Pending) {
            throw new DomainException('Sales return is no longer pending.');
        }

        return $locked;
    }

    /**
     * The submitted quantities, with the despatch's own money attached to each.
     *
     * The order's lines are read once and keyed, rather than a query per row. A line whose source
     * does not resolve is dropped rather than throwing: the rules have just proved every one of
     * them belongs to this order, so this is the analyser being told the shape.
     *
     * @param  list<array{sales_order_item_id: int, quantity: string}>  $lines
     * @return list<array{sales_order_item_id: int, quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>
     */
    private static function price(array $lines, SalesOrder $order): array
    {
        $sources = $order->items()
            ->whereKey(array_column($lines, 'sales_order_item_id'))
            ->get()
            ->keyBy('id');

        $priced = [];

        foreach ($lines as $line) {
            $source = $sources->get($line['sales_order_item_id']);

            if (! $source instanceof SalesOrderItem) {
                continue;
            }

            $priced[] = [
                'sales_order_item_id' => $source->id,
                // The one number that is the person's.
                'quantity' => $line['quantity'],
                'unit_price' => $source->unit_price,
                'discount_type' => $source->discount_type,
                'discount_value' => $source->discount_value,
                'taxable' => $source->taxable,
            ];
        }

        return $priced;
    }

    /**
     * A new return.
     *
     * The three completion columns are named as nulls rather than left to the schema, because the
     * set of them is what says this has not been completed.
     *
     * @param  array{sales_order_id: int, reason: ReturnReason, notes: string|null}  $fields
     * @param  array{subtotal: string, discount_total: string, tax_total: string, total: string}  $totals
     */
    private function open(SalesOrder $order, array $fields, array $totals, ?User $user): SalesReturn
    {
        return SalesReturn::query()->forceCreate([
            // Allocated under the sequence row's lock, which this transaction holds.
            'number' => $this->numbers->next(DocumentType::SalesReturn),
            'sales_order_id' => $order->id,
            'status' => ReturnStatus::Pending,
            'reason' => $fields['reason'],
            // All three off the order — see the class note.
            'currency' => $order->currency,
            'exchange_rate' => $order->exchange_rate,
            'tax_rate' => $order->tax_rate,
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'notes' => $fields['notes'],
            'created_by' => $user?->id,
            'completed_by' => null,
            'completed_at' => null,
            'completed_warehouse_id' => null,
        ]);
    }

    /**
     * A pending return rewritten, lines and all.
     *
     * `number`, `status`, `sales_order_id` and the three completion columns are conspicuously
     * absent: an edit changes how much is coming back and why, never what the document is, what
     * it credits, or what has happened to it.
     *
     * The three money columns are re-copied from the order, which is a no-op today — a fulfilled
     * order is frozen — and is cheap symmetry with {@see open()}, so neither path is the one that
     * has to be remembered.
     *
     * @param  array{sales_order_id: int, reason: ReturnReason, notes: string|null}  $fields
     * @param  array{subtotal: string, discount_total: string, tax_total: string, total: string}  $totals
     */
    private function revise(SalesReturn $return, SalesOrder $order, array $fields, array $totals): SalesReturn
    {
        $return->forceFill([
            'reason' => $fields['reason'],
            'currency' => $order->currency,
            'exchange_rate' => $order->exchange_rate,
            'tax_rate' => $order->tax_rate,
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'notes' => $fields['notes'],
        ])->save();

        // Delete then insert, rather than a diff. What arrived is the complete list the return
        // should have afterwards, nothing anywhere points at a `sales_return_items.id`, and the
        // transaction is what makes the intermediate emptiness safe.
        $return->items()->delete();

        return $return;
    }

    /**
     * The lines, each carrying what it credits.
     *
     * `line_total` is computed by the same {@see OrderTotals::line()} the return's own subtotal
     * was summed from — one definition, so a line and the document it is on cannot disagree about
     * the same discount. At the *returned* quantity, with the despatch's price.
     *
     * @param  list<array{sales_order_item_id: int, quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $priced
     */
    private function writeLines(SalesReturn $return, array $priced): void
    {
        foreach ($priced as $line) {
            $amounts = OrderTotals::line(
                $line['quantity'],
                $line['unit_price'],
                $line['discount_type'],
                $line['discount_value'],
            );

            // The relation names `sales_return_id`; every column after it is named here, because
            // this table declares no `$fillable` and a write has to say what it sets.
            $return->items()->forceCreate([
                'sales_order_item_id' => $line['sales_order_item_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'taxable' => $line['taxable'],
                'line_total' => $amounts['net'],
            ]);
        }
    }

    /**
     * The priced lines in the shape {@see OrderTotals::forOrder()} reads.
     *
     * **No rename here, unlike the purchase side.** That helper names the money `unit_price`
     * because it serves both sides of the trade, and a sales document already stores it under
     * that name — so where `SavePurchaseReturn::moneyLines()` exists to translate `unit_cost`,
     * this one only drops the line id.
     *
     * @param  list<array{sales_order_item_id: int, quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $priced
     * @return list<array{quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>
     */
    private static function moneyLines(array $priced): array
    {
        return array_map(static fn (array $line): array => [
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'discount_type' => $line['discount_type'],
            'discount_value' => $line['discount_value'],
            'taxable' => $line['taxable'],
        ], $priced);
    }
}
