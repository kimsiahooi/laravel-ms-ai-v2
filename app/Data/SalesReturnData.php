<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\SalesReturn;
use App\Support\Decimals;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A sales return as every screen reads it: what it credits, why, what it comes to, and — once it
 * has happened — where the goods went back.
 *
 * The mirror of {@see PurchaseReturnData}. `customer`, `created_by`, `completed_by` and
 * `completed_warehouse` are *names* rather than ids, because the screens read them and nothing
 * links to them; an id would only be a second lookup on the client.
 *
 * `status` and `reason` travel as **values**, and the browser composes `returns.status.{value}`
 * and `returns.reason.{value}` — the house pattern, and the same shared namespace the purchase
 * side reads, so a case added without its three translations is a `tsc` error rather than a
 * blank chip.
 */
#[TypeScript]
final class SalesReturnData extends Data
{
    public function __construct(
        public int $id,
        public string $number,
        /** The despatch being credited — always present, the column is not nullable. */
        public int $sales_order_id,
        public string $sales_order_number,
        /** Null once the customer has been hard-deleted out of the catalogue. */
        public ?string $customer,
        public ReturnStatus $status,
        public ReturnReason $reason,
        public string $currency,
        public string $exchange_rate,
        public string $tax_rate,
        public string $subtotal,
        public string $discount_total,
        public string $tax_total,
        public string $total,
        public ?string $notes,
        /** Who raised it. Null once that person has been force-deleted. */
        public ?string $created_by,
        /**
         * The completion, as three nulls until it happens — the shape
         * {@see PurchaseReturnData} uses, and {@see SalesOrderData} before it.
         */
        public ?string $completed_by,
        public ?string $completed_at,
        public ?string $completed_warehouse,
        /** From `withCount('items as line_count')` — see the note below. */
        public int $line_count,
        public string $created_at,
    ) {}

    public static function fromSalesReturn(SalesReturn $return): self
    {
        $order = $return->salesOrder;

        return new self(
            id: $return->id,
            number: $return->number,
            sales_order_id: $return->sales_order_id,
            sales_order_number: $order->number,
            customer: $order->customer?->name,
            status: $return->status,
            reason: $return->reason,
            currency: $return->currency,
            // Rates are trimmed rather than rounded: `4.350000` is six digits of noise, and a
            // rate is not money — rounding it to the currency's scale would be wrong.
            exchange_rate: Decimals::trim($return->exchange_rate),
            tax_rate: Decimals::trim($return->tax_rate),
            // Money, and only ever displayed: rounded to what the currency can express, so the
            // four figures add up on screen the way they add up in the column.
            subtotal: Money::roundTo($return->subtotal, $return->currency),
            discount_total: Money::roundTo($return->discount_total, $return->currency),
            tax_total: Money::roundTo($return->tax_total, $return->currency),
            total: Money::roundTo($return->total, $return->currency),
            notes: $return->notes,
            created_by: $return->creator?->name,
            completed_by: $return->completer?->name,
            completed_at: $return->completed_at?->toIso8601String(),
            completed_warehouse: $return->completedWarehouse?->name,
            // Read off the aggregate alias rather than counting a loaded relation, which would
            // be a query per row on the list. A forgotten `withCount` shows zero next to a
            // document that visibly has lines — see the controller, which adds it in both
            // places.
            line_count: (int) ($return->getAttribute('line_count') ?? 0),
            created_at: $return->created_at->toIso8601String(),
        );
    }
}
