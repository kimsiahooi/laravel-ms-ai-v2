<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;
use App\Support\StockItem;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Recording one movement.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/stock-movement.ts.
 *
 * There is no update or delete request to pair with this, and that is the design: the
 * ledger is append-only. A mistake is corrected by recording the opposite movement,
 * which is what keeps the history readable afterwards.
 */
final class StockMovementRequest extends TenantFormRequest
{
    /**
     * What the form asks for, and what each one means for the quantity.
     *
     * `set` is the odd one: its quantity is not an amount to move but the level to end
     * up at, and StockService works out the difference under the lock. Two people
     * counting the same shelf therefore cannot both compute a delta from the same
     * starting number.
     */
    public const TYPES = ['in', 'out', 'set'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Computed before the array rather than inline in it. The ternary read fine, but
        // `check:i18n` parses this file as text to find every rule in use, and a quoted
        // `'set'` sitting inside the rules literal is indistinguishable to it from a
        // rule name — it reported two rules that do not exist. A gate that can be
        // confused by the code it checks is a gate that gets ignored, so the code moves.
        $bound = $this->input('type') === 'set' ? 'gte:0' : 'gt:0';

        return [
            'warehouse_id' => ['required', ActiveExists::of('warehouses')],
            // One field, not a type and an id — see StockItem on why.
            'item' => ['required', 'string', $this->itemExists()],
            'type' => ['required', Rule::in(self::TYPES)],
            // A magnitude for in and out; the level to end at for set.
            //
            // Moving zero in or out is refused: it appends a row to a ledger nothing
            // deletes, saying nothing happened. Setting *to* zero is a real correction —
            // "the shelf is empty" — so zero stays legal there, and that is the whole
            // reason the bound is not a constant.
            'quantity' => ['required', ...$this->decimalRules($bound)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The picked item must name a row that exists and is not trashed.
     *
     * A closure rather than `exists`, because which table to look in is part of the
     * value — see {@see StockItem::decode()}, which is the check as well as the parser.
     */
    private function itemExists(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || StockItem::decode($value) === null) {
                $fail('validation.exists')->translate();
            }
        };
    }
}
