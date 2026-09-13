<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Support\Decimals;
use App\Support\Money;
use App\Support\OrderTotals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One sales order, as the list sends it and as the detail and form screens read its header.
 * **No lines.**
 *
 * v1 embedded every line in every list row — a page of twenty orders carried every product
 * on all twenty — and then summed them in PHP to arrive at a total it had never stored. Here
 * the lines travel on their own ({@see SalesOrderItemData}), fetched by the two screens that
 * show them, and `line_count` comes off a `withCount` so the list can say "12 lines" without
 * loading twelve rows.
 *
 * **Every figure is a decimal string, and every one was read from a column.** v1's DTO
 * declared `float $total` and computed it on each read — money in binary floating point, and
 * a figure re-derived rather than recorded, so no two readers were guaranteed the same answer
 * and none could be reconciled against an invoice. These four are what {@see OrderTotals}
 * decided when the order was saved.
 *
 * **Values, not sentences**, like {@see PurchaseOrderData}: the status is an enum case the
 * browser looks up in `lang/`, where v1 shipped a `status_label` in English alongside it.
 * `customer`, `created_by`, `fulfilled_by` and `fulfilled_warehouse` are *names*, not the id
 * columns they are read from — a list showing who is not offering to filter by them.
 */
#[TypeScript]
final class SalesOrderData extends Data
{
    public function __construct(
        public int $id,
        /** Allocated by DocumentNumberGenerator — `SO-2026-0001`. Never typed. */
        public string $number,
        /** Null once the customer has been hard-deleted; an archived one still names itself. */
        public ?string $customer,
        public ?int $customer_id,
        public SalesOrderStatus $status,
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
        /**
         * `Y-m-d`, or `Y-m-d H:i` when a delivery time was agreed.
         *
         * Deliberately not an instant. A promised day is a date the business wrote down, and
         * nothing — not the reader's browser, not the workspace timezone setting — converts
         * it. That is what keeps it meaning the same day forever.
         */
        public ?string $expected_date,
        /** Who took it; null for an order created by a console command. */
        public ?string $created_by,
        /** Who shipped it; null until fulfilled, and forever if cancelled. */
        public ?string $fulfilled_by,
        public ?string $fulfilled_at,
        /** Which warehouse the goods left; null until fulfilled. */
        public ?string $fulfilled_warehouse,
        public int $line_count,
        public string $created_at,
    ) {}

    /**
     * The four money figures are rounded to what the currency can actually express, and the
     * two rates are trimmed.
     *
     * The columns are `decimal(15,4)` and always return four places, which is right for
     * arithmetic and wrong for a screen. The distinction is not cosmetic: `formatMoney` in
     * the browser takes its scale from the string it is given precisely because the server
     * has already rounded — so `Money::roundTo` here is what makes the stored total render
     * byte for byte like the running estimate the order form computed from the same lines. A
     * rate is not money and has no currency scale, so it only loses trailing zeros:
     * `1.000000` is a rate of one, and six zeros say nothing but how the column was declared.
     *
     * `line_count` arrives as a `withCount` alias named exactly like the field it fills, so
     * the query producing it and the row showing it cannot drift apart under a rename.
     * `getAttribute` rather than property access because it is not a column, and a caller
     * that forgets the alias gets zero — which fails loudly on screen, next to a document
     * that plainly has lines on it.
     */
    public static function fromSalesOrder(SalesOrder $order): self
    {
        $currency = $order->currency;

        return new self(
            id: $order->id,
            number: $order->number,
            // Loaded withTrashed by the relation, so an archived customer still names
            // itself; null only after a hard delete, which the FK nulls rather than taking
            // the order with it.
            customer: $order->customer?->name,
            customer_id: $order->customer_id,
            status: $order->status,
            currency: $currency,
            exchange_rate: Decimals::trim((string) $order->exchange_rate),
            tax_rate: Decimals::trim((string) $order->tax_rate),
            subtotal: Money::roundTo((string) $order->subtotal, $currency),
            discount_total: Money::roundTo((string) $order->discount_total, $currency),
            tax_total: Money::roundTo((string) $order->tax_total, $currency),
            total: Money::roundTo((string) $order->total, $currency),
            notes: $order->notes,
            // `Y-m-d` or `Y-m-d H:i` — the wire shape the form sent, unchanged. Not ISO-8601
            // like the instants beside it: a promised delivery is a date the business wrote
            // down rather than a moment, so nothing converts it on the way out any more than
            // on the way in. See SalesOrderRequest::expectedInstant().
            expected_date: $order->expected_date === null
                ? null
                : $order->expected_date->format(
                    $order->expected_date->format('H:i') === '00:00' ? 'Y-m-d' : 'Y-m-d H:i',
                ),
            created_by: $order->creator?->name,
            fulfilled_by: $order->fulfiller?->name,
            fulfilled_at: $order->fulfilled_at?->toIso8601String(),
            fulfilled_warehouse: $order->fulfilledWarehouse?->name,
            line_count: (int) $order->getAttribute('line_count'),
            created_at: $order->created_at->toIso8601String(),
        );
    }
}
