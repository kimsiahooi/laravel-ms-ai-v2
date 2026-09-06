<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Support\Decimals;
use App\Support\Money;
use App\Support\OrderTotals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One purchase order, as the list sends it and as the detail and form screens read its
 * header. **No lines.**
 *
 * v1 embedded every line in every list row — a page of twenty orders carried every
 * material on all twenty — and then summed them in PHP to arrive at a total it had never
 * stored. Here the lines travel on their own ({@see PurchaseOrderItemData}), fetched by
 * the two screens that show them, and `line_count` comes off a `withCount` so the list can
 * say "12 lines" without loading twelve rows.
 *
 * **Every figure is a decimal string, and every one of them was read from a column.** v1's
 * DTO declared `float $total` and computed it on each read as
 * `$item->quantity * $item->unit_cost` — money in binary floating point, and a figure
 * re-derived rather than recorded, so no two readers were guaranteed the same answer and
 * none of them could be reconciled against an invoice. These four are what
 * {@see OrderTotals} decided when the order was saved.
 *
 * **Values, not sentences**, like {@see StockMovementData}: the status is an enum case the
 * browser looks up in `lang/`, where v1 shipped a `status_label` in English alongside it.
 * `supplier`, `created_by`, `received_by` and `received_warehouse` are *names*, not the id
 * columns they are read from — a list showing who is not offering to filter by them.
 */
#[TypeScript]
final class PurchaseOrderData extends Data
{
    public function __construct(
        public int $id,
        /** Allocated by DocumentNumberGenerator — `PO-2026-0001`. Never typed. */
        public string $number,
        /** Null once the supplier has been hard-deleted; an archived one still names itself. */
        public ?string $supplier,
        public ?int $supplier_id,
        public PurchaseOrderStatus $status,
        public string $currency,
        /** Base-currency units per one unit of `currency`. */
        public string $exchange_rate,
        /** A percentage — `'6'`, not `'0.06'` — as snapshotted when the order was raised. */
        public string $tax_rate,
        public string $subtotal,
        public string $discount_total,
        public string $tax_total,
        public string $total,
        public ?string $notes,
        /** `Y-m-d`. A promised day, with no time of day to shift across a zone. */
        public ?string $expected_date,
        /** Who raised it; null for an order created by a console command. */
        public ?string $created_by,
        /** Who booked the goods in; null until the order is received, and forever if cancelled. */
        public ?string $received_by,
        public ?string $received_at,
        /** Where the goods landed; null until received. */
        public ?string $received_warehouse,
        public int $line_count,
        public string $created_at,
    ) {}

    /**
     * The four money figures are rounded to what the currency can actually express, and
     * the two rates are trimmed.
     *
     * The columns are `decimal(15,4)` and always return four places, which is right for
     * arithmetic and wrong for a screen. The distinction between the two treatments is not
     * cosmetic: `formatMoney` in the browser takes the scale from the string it is given
     * precisely because the server has already rounded — so `Money::roundTo` here is what
     * makes the stored total render byte for byte like the running estimate the order form
     * computed from the same lines. A rate is not money and has no currency scale, so it
     * only loses its trailing zeros: `1.000000` is a rate of one, and six zeros say
     * nothing but how the column was declared.
     *
     * `line_count` arrives as a `withCount` alias named exactly like the field it fills,
     * so the query that produces it and the row that shows it cannot drift apart under a
     * rename. `getAttribute` rather than property access because it is not a column, and
     * a caller that forgets the alias gets zero — which fails loudly on screen, next to a
     * document that plainly has lines on it.
     */
    public static function fromPurchaseOrder(PurchaseOrder $order): self
    {
        $currency = $order->currency;

        return new self(
            id: $order->id,
            number: $order->number,
            // Loaded withTrashed by the relation, so an archived supplier still names
            // itself; null only after a hard delete, which the FK nulls rather than
            // taking the order with it.
            supplier: $order->supplier?->name,
            supplier_id: $order->supplier_id,
            status: $order->status,
            currency: $currency,
            exchange_rate: Decimals::trim((string) $order->exchange_rate),
            tax_rate: Decimals::trim((string) $order->tax_rate),
            subtotal: Money::roundTo((string) $order->subtotal, $currency),
            discount_total: Money::roundTo((string) $order->discount_total, $currency),
            tax_total: Money::roundTo((string) $order->tax_total, $currency),
            total: Money::roundTo((string) $order->total, $currency),
            notes: $order->notes,
            // ISO-8601, like every other instant this DTO sends: the column holds a
            // moment now, and the screen renders it on the reader's clock rather than
            // receiving a day that has already had a zone baked out of it.
            expected_date: $order->expected_date?->toIso8601String(),
            created_by: $order->creator?->name,
            received_by: $order->receiver?->name,
            received_at: $order->received_at?->toIso8601String(),
            received_warehouse: $order->receivedWarehouse?->name,
            line_count: (int) $order->getAttribute('line_count'),
            created_at: $order->created_at->toIso8601String(),
        );
    }
}
