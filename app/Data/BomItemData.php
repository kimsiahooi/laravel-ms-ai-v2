<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\BomItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a bill of materials, as the editor receives it.
 *
 * Both the material's id and its name travel, for the same reason they do on
 * {@see ProductData}: the id seeds the picker, the name is what the line reads as while
 * the popover is shut.
 *
 * `quantity` is a **string**, and a trimmed one. The column is decimal(15,4), so the
 * database hands back `2.5000` — correct, and wrong to put in a text box, where it
 * reads as a value somebody deliberately typed to four places. A float would trim
 * itself but re-opens the question this column exists to close: binary floating point
 * cannot hold 0.1, and this number is multiplied by an order size. Trimming the string
 * keeps the exact value and shows it the way it was entered.
 */
#[TypeScript]
final class BomItemData extends Data
{
    public function __construct(
        public int $id,
        public int $raw_material_id,
        public string $name,
        public string $quantity,
    ) {}

    public static function fromBomItem(BomItem $item): self
    {
        return new self(
            id: $item->id,
            raw_material_id: $item->raw_material_id,
            // Never null: the FK is NOT NULL and cascades, so a line cannot outlive
            // its material, and the relation is withTrashed() so a soft-deleted one
            // still resolves rather than blanking the row.
            name: $item->rawMaterial->name,
            quantity: self::trim($item->quantity),
        );
    }

    /**
     * `2.5000` → `2.5`, `10.0000` → `10`, `0.1000` → `0.1`.
     *
     * The `.` guard is load-bearing: trimming zeros off `10` without it gives `1`.
     */
    private static function trim(string $quantity): string
    {
        return str_contains($quantity, '.')
            ? rtrim(rtrim($quantity, '0'), '.')
            : $quantity;
    }
}
