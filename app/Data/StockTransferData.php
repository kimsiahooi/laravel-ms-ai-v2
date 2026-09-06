<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockItemType;
use App\Models\StockTransfer;
use App\Support\Decimals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One transfer, as the listing sends it.
 *
 * **Every field is a value, not a sentence** — the same rule {@see StockMovementData}
 * follows. v1 shipped `"Oak board · Raw material"` and `"KL HQ · Main Store"` already
 * joined, which is three strings that can only ever be English; here each part arrives
 * separately and the screen decides how to put them together.
 *
 * Both endpoints send their site as well as their name, because a warehouse called
 * "Main store" exists at more than one site and the pair is what identifies it.
 */
#[TypeScript]
final class StockTransferData extends Data
{
    public function __construct(
        public int $id,
        /** Null once the product or material has been force-deleted from the catalog. */
        public ?string $item,
        public ?string $item_sku,
        public StockItemType $item_type,
        public string $from_warehouse,
        public string $from_site,
        public string $to_warehouse,
        public string $to_site,
        /** A positive magnitude; `from` and `to` carry the direction. */
        public string $quantity,
        public ?string $user,
        public ?string $notes,
        public string $created_at,
    ) {}

    public static function fromStockTransfer(StockTransfer $transfer): self
    {
        // Both warehouses are loaded withTrashed so they always resolve; `stockable`
        // may not, because a force-delete takes the row with it — and a transfer that
        // can no longer say what moved is still a transfer, so the screen shows a dash.
        $item = $transfer->stockable;

        return new self(
            id: $transfer->id,
            item: $item?->name,
            item_sku: $item?->sku,
            item_type: StockItemType::from($transfer->stockable_type),
            from_warehouse: $transfer->fromWarehouse->name,
            from_site: $transfer->fromWarehouse->location->name,
            to_warehouse: $transfer->toWarehouse->name,
            to_site: $transfer->toWarehouse->location->name,
            // Trimmed for reading: the column always returns 40.5000, and four digits of
            // trailing zeros on every row say nothing but how it was declared.
            quantity: Decimals::trim((string) $transfer->quantity),
            user: $transfer->user?->name,
            notes: $transfer->notes,
            created_at: $transfer->created_at->toIso8601String(),
        );
    }
}
