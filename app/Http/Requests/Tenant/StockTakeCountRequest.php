<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\StockTake;
use Illuminate\Validation\Rule;

/**
 * One counted line, saved the moment somebody finishes typing it.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/stock-take-count.ts.
 *
 * **One line per request, not a whole sheet.** v1 posted the entire count in a single
 * form at the end, so a refreshed tab, a flat battery or a closed laptop threw away an
 * afternoon of walking the shelves — and its request had to cap `items` at five thousand
 * to stop a sheet timing out. A count that is saved as it is entered has no cap to
 * choose and nothing to lose.
 *
 * **`counted_quantity` is nullable, and null is not an oversight — it is "not counted".**
 * Clearing the box has to be a way back out of a number typed by mistake; without it the
 * only correction available is a wrong count, and a wrong count posts.
 *
 * **`gte:0`, not the default `gt:0`.** "The shelf is empty" is the single most important
 * thing a count can discover, and it is the one thing a rule requiring a positive number
 * cannot express.
 */
final class StockTakeCountRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Built above the array rather than inside it, for the reason StockMovementRequest
        // gives: `check:i18n` and `check:validation` read this literal as text, and a
        // quoted table or column name sitting in it is indistinguishable to them from a
        // rule name.
        $take = $this->route('stockTake');
        $takeId = $take instanceof StockTake ? $take->id : 0;
        // Scoped to this take's own lines. Without the `where` the rule proves only that
        // some line with that id exists somewhere, so anybody holding one sheet could
        // write counts onto every other sheet in the workspace. The `0` fallback is
        // unreachable — the route binds the model — and exists so a missing binding
        // refuses everything rather than accidentally scoping to nothing.
        $ownLine = Rule::exists('stock_take_items', 'id')->where('stock_take_id', $takeId);

        return [
            'line' => ['required', 'integer', $ownLine],
            'counted_quantity' => ['nullable', ...$this->decimalRules('gte:0')],
        ];
    }
}
