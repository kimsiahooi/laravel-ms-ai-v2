<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\BusinessSetting;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use App\Support\OrderTotals;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Raises a purchase order, or rewrites a pending one, together with its lines.
 *
 * **Everything here is one transaction, and each of the three writes explains why.** The
 * number is allocated under a row lock that is only held for as long as the transaction
 * is ({@see DocumentNumberGenerator} refuses to run outside one), so committing the
 * header separately would release the lock before the order exists and hand the same
 * number to somebody else. The lines are deleted and rewritten on an edit, so a failure
 * between the two would leave a priced order with nothing on it. And the totals are
 * derived from the lines, so an order whose header committed and whose lines did not
 * would state a figure it cannot account for.
 *
 * **No total is ever accepted from the request.** {@see OrderTotals} is the only thing
 * that decides what an order comes to; the form sends lines and mirrors the arithmetic in
 * the browser so the figure moves as somebody types, and what is stored is worked out
 * again here. v1 stored nothing at all — its totals were floats summed inside a DTO on
 * every read — so there was no stored figure to disagree with, and no figure to reconcile
 * an invoice against either.
 *
 * **Delete-then-insert on an edit, not a diff**, exactly as {@see ReplaceBom} replaces a
 * bill and for the same reason: what arrives is the complete list of lines the order
 * should have afterwards, nothing points at a `purchase_order_items.id`, and reconciling
 * row by row would be a great deal of code to preserve ids nobody refers to. The
 * transaction is what makes it safe — between the delete and the last insert the order
 * has no lines, and a failure there would leave it that way permanently.
 *
 * **The tax rate is snapshotted on both paths.** An order keeps the rate it was raised
 * under, so the settings row is read once here and copied onto the order rather than
 * looked up whenever somebody renders it; re-reading it on an edit is deliberate, because
 * an order being edited is an order being raised again.
 */
final class OpenPurchaseOrder
{
    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /**
     * @param  array{supplier_id: int, currency: string, exchange_rate: string, expected_date: CarbonImmutable|null, notes: string|null}  $header
     *                                                                                                                                             Validated and typed by {@see PurchaseOrderRequest}.
     * @param  list<array{raw_material_id: int, quantity: string, unit_cost: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $lines
     *                                                                                                                                                             The complete list the order should have afterwards, in order.
     * @param  PurchaseOrder|null  $order  the pending order being rewritten, or null to raise a new
     *                                     one. One method rather than two because the only difference
     *                                     between raising an order and correcting one is whether a
     *                                     number has to be allocated — splitting them would duplicate
     *                                     the lines and the totals, which are the parts worth getting
     *                                     right once.
     */
    public function handle(array $header, array $lines, ?User $user = null, ?PurchaseOrder $order = null): PurchaseOrder
    {
        return DB::transaction(function () use ($header, $lines, $user, $order): PurchaseOrder {
            // Read once, used twice: the figure that is stored on the order and the rate
            // the totals are computed at have to be the same number, and a second read
            // between them could straddle somebody saving the settings screen.
            $taxRate = (string) BusinessSetting::current()->tax_rate;

            $totals = OrderTotals::forOrder(
                self::moneyLines($lines),
                $taxRate,
                $header['currency'],
            );

            $order = $order === null
                ? $this->open($header, $totals, $taxRate, $user)
                : $this->revise($order, $header, $totals, $taxRate);

            $this->writeLines($order, $lines);

            return $order;
        });
    }

    /**
     * A new order, numbered and pending.
     *
     * `forceCreate` for the reason the model gives: nothing about a purchase order is
     * mass-assignable from a request, so every column is named right here. The three
     * receipt columns are named as nulls rather than left to the schema, because the set
     * of them is what says this has not been received.
     *
     * @param  array{supplier_id: int, currency: string, exchange_rate: string, expected_date: CarbonImmutable|null, notes: string|null}  $header
     * @param  array{subtotal: string, discount_total: string, tax_total: string, total: string}  $totals
     */
    private function open(array $header, array $totals, string $taxRate, ?User $user): PurchaseOrder
    {
        return PurchaseOrder::query()->forceCreate([
            // Allocated under the sequence row's lock, which this transaction holds. v1
            // let somebody type this into a box on the form.
            'number' => $this->numbers->next(DocumentType::PurchaseOrder),
            'supplier_id' => $header['supplier_id'],
            'status' => PurchaseOrderStatus::Pending,
            'currency' => $header['currency'],
            'exchange_rate' => $header['exchange_rate'],
            'tax_rate' => $taxRate,
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'notes' => $header['notes'],
            'expected_date' => $header['expected_date'],
            'created_by' => $user?->id,
            'received_by' => null,
            'received_at' => null,
            'received_warehouse_id' => null,
        ]);
    }

    /**
     * A pending order rewritten, lines and all.
     *
     * Whether it is still pending is the caller's question, not this one's — the
     * controller refuses an edit against a received order with a sentence a person can
     * read, and there is no lock to take here because an order being edited is not being
     * raced by anything that changes stock.
     *
     * `number`, `status` and the three receipt columns are conspicuously absent: an
     * edit changes what was agreed, never what the document is or what has happened to
     * it. v1 made all five fillable and passed the request array straight through.
     *
     * @param  array{supplier_id: int, currency: string, exchange_rate: string, expected_date: CarbonImmutable|null, notes: string|null}  $header
     * @param  array{subtotal: string, discount_total: string, tax_total: string, total: string}  $totals
     */
    private function revise(PurchaseOrder $order, array $header, array $totals, string $taxRate): PurchaseOrder
    {
        $order->forceFill([
            'supplier_id' => $header['supplier_id'],
            'currency' => $header['currency'],
            'exchange_rate' => $header['exchange_rate'],
            'tax_rate' => $taxRate,
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'notes' => $header['notes'],
            'expected_date' => $header['expected_date'],
        ])->save();

        // Through the relation, not `truncate()` on the table: the lines being replaced
        // are this order's, and another order's are one row away in the same tenant
        // database.
        $order->items()->delete();

        return $order;
    }

    /**
     * The lines, each carrying what it comes to.
     *
     * `line_total` is stored rather than recomputed on read for the reason the migration
     * gives, and it is computed by the same {@see OrderTotals::line()} the order's own
     * subtotal was summed from — one definition, so a line and the order it is on cannot
     * disagree about the same discount.
     *
     * @param  list<array{raw_material_id: int, quantity: string, unit_cost: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $lines
     */
    private function writeLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $amounts = OrderTotals::line(
                $line['quantity'],
                $line['unit_cost'],
                $line['discount_type'],
                $line['discount_value'],
            );

            // The relation names `purchase_order_id`; every column after it is named
            // here, because this table declares no `$fillable` and a write has to say
            // exactly what it sets.
            $order->items()->forceCreate([
                'raw_material_id' => $line['raw_material_id'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'taxable' => $line['taxable'],
                'line_total' => $amounts['net'],
            ]);
        }
    }

    /**
     * The request's lines in the shape {@see OrderTotals::forOrder()} reads.
     *
     * The only difference is the name of one key — an order line calls it `unit_cost`
     * because that is what a purchase pays, and the money helper calls it `unit_price`
     * because it serves sales orders too. Renamed here rather than in either of them, so
     * neither side has to know about the other's vocabulary.
     *
     * @param  list<array{raw_material_id: int, quantity: string, unit_cost: string, discount_type: DiscountType, discount_value: string, taxable: bool}>  $lines
     * @return list<array{quantity: string, unit_price: string, discount_type: DiscountType, discount_value: string, taxable: bool}>
     */
    private static function moneyLines(array $lines): array
    {
        return array_map(static fn (array $line): array => [
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_cost'],
            'discount_type' => $line['discount_type'],
            'discount_value' => $line['discount_value'],
            'taxable' => $line['taxable'],
        ], $lines);
    }
}
