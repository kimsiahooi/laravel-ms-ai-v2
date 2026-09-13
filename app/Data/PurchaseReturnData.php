<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\PurchaseReturn;
use App\Support\Decimals;
use App\Support\Money;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A purchase return's header — what it credits, why, and what it comes to.
 *
 * **Header only, no lines**, the shape {@see PurchaseOrderData} keeps: the list needs one row
 * per document and the show page fetches its lines separately, so a list of twenty returns is
 * twenty of these rather than twenty documents.
 *
 * **The counterparty is read through the order, not copied.** There is no `supplier_id` on the
 * table — a return credits a delivery, and who that delivery was from is the order's answer. One
 * fact, one home.
 *
 * **No labels cross the wire.** `status` and `reason` travel as enum cases and the browser
 * composes `returns.status.{value}` and `returns.reason.{value}`, the same pattern
 * {@see PurchaseOrderData} uses — so a case added without its three translations is a `tsc`
 * error rather than a blank chip.
 */
#[TypeScript]
final class PurchaseReturnData extends Data
{
    public function __construct(
        public int $id,
        public string $number,
        /** The delivery being credited — always present, the column is not nullable. */
        public int $purchase_order_id,
        public string $purchase_order_number,
        /** Null once the supplier has been hard-deleted out of the catalogue. */
        public ?string $supplier,
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
        /** From `withCount('items as line_count')` — see the note below. */
        public int $line_count,
        public string $created_at,
    ) {}

    public static function fromPurchaseReturn(PurchaseReturn $return): self
    {
        $order = $return->purchaseOrder;

        return new self(
            id: $return->id,
            number: $return->number,
            purchase_order_id: $return->purchase_order_id,
            purchase_order_number: $order->number,
            supplier: $order->supplier?->name,
            status: $return->status,
            reason: $return->reason,
            currency: $return->currency,
            // Rates are trimmed rather than rounded: `4.350000` is six digits of noise, and
            // a rate is not money — rounding it to the currency's scale would be wrong.
            exchange_rate: Decimals::trim($return->exchange_rate),
            tax_rate: Decimals::trim($return->tax_rate),
            // Money, and only ever displayed: rounded to what the currency can express, so
            // the four figures add up on screen the way they add up in the column.
            subtotal: Money::roundTo($return->subtotal, $return->currency),
            discount_total: Money::roundTo($return->discount_total, $return->currency),
            tax_total: Money::roundTo($return->tax_total, $return->currency),
            total: Money::roundTo($return->total, $return->currency),
            notes: $return->notes,
            created_by: $return->creator?->name,
            // Read off the aggregate alias rather than counting a loaded relation, which
            // would be a query per row on the list. A forgotten `withCount` shows zero next
            // to a document that visibly has lines — see the controller, which adds it in
            // both places.
            line_count: (int) ($return->getAttribute('line_count') ?? 0),
            created_at: $return->created_at->toIso8601String(),
        );
    }
}
