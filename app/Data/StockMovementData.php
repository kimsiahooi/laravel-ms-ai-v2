<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MovementSource;
use App\Enums\StockItemType;
use App\Enums\StockMovementReason;
use App\Models\StockMovement;
use App\Support\Decimals;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of the ledger, as the listing sends it.
 *
 * **Every field is a value, not a sentence.** v1's shipped `"KL HQ · Main Store"` and
 * `"Oak board · Raw material"` and an English `reason` label — three strings assembled
 * on the server, which is three strings that can only ever be English. Here the site and
 * the warehouse arrive separately and the screen decides how to join them, and the type
 * and the reason arrive as enum values the browser looks up in `lang/`.
 *
 * `quantity` is a signed decimal string: positive in, negative out. A string for the
 * same reason StockService works in them, and signed because the ledger has no direction
 * column — see the migration.
 */
#[TypeScript]
final class StockMovementData extends Data
{
    public function __construct(
        public int $id,
        public string $warehouse,
        public string $site,
        /** Null once the product or material has been force-deleted from the catalog. */
        public ?string $item,
        public ?string $item_sku,
        public StockItemType $item_type,
        public string $quantity,
        public StockMovementReason $reason,
        public ?string $user,
        /** What a person typed. Never a system reference — that is what `source` is. */
        public ?string $notes,
        /**
         * The document that caused this row, as a value the screen turns into words.
         * Null for a hand-recorded adjustment and for every row written before the
         * `source` columns existed.
         */
        public ?MovementSource $source_type,
        public ?int $source_id,
        public string $created_at,
    ) {}

    public static function fromStockMovement(StockMovement $movement): self
    {
        // The ledger is append-only and outlives what it names. `warehouse` is loaded
        // withTrashed so it always resolves; `stockable` may not, because a force-delete
        // takes the row with it — and a movement that can no longer say what moved is
        // still a movement, so the screen shows a dash rather than the list breaking.
        $item = $movement->stockable;

        return new self(
            id: $movement->id,
            warehouse: $movement->warehouse->name,
            site: $movement->warehouse->location->name,
            item: $item?->name,
            item_sku: $item?->sku,
            item_type: StockItemType::from($movement->stockable_type),
            // Trimmed for reading: the column always returns 40.5000, and four
            // digits of trailing zeros on every ledger row say nothing but how the
            // column was declared. The sign survives — it is the direction.
            quantity: Decimals::trim((string) $movement->quantity),
            reason: $movement->reason,
            user: $movement->user?->name,
            notes: $movement->notes,
            // tryFrom, not from: the column is written through the morph map, and a key
            // this enum has not been taught must not break the whole ledger.
            source_type: $movement->source_type === null
                ? null
                : MovementSource::tryFrom($movement->source_type),
            source_id: $movement->source_id,
            created_at: $movement->created_at->toIso8601String(),
        );
    }
}
