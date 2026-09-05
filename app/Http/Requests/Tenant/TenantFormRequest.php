<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every per-tenant form request.
 *
 * The routes are already behind `auth:web` and AuthorizeTenantRoute, so this
 * `authorize()` is belt-and-suspenders — it refuses a request that somehow arrived
 * with no bound user. Subclasses declare `rules()` and use the helper below.
 */
abstract class TenantFormRequest extends FormRequest
{
    /**
     * Decimal places a `decimal(15,4)` column stores.
     *
     * Four, because the first of these columns is a per-unit BOM quantity, where the
     * small numbers live — 0.0125 kg of pigment per unit. Every quantity and money
     * column in the schema uses the same shape, so the scale is declared once here.
     */
    protected const DECIMAL_SCALE = 4;

    /**
     * The largest value a `decimal(15,4)` column holds: 11 integer digits, since 4 of
     * the 15 are spent after the point.
     *
     * Capping it in validation is not tidiness. MySQL in strict mode *errors* on too
     * many integer digits, so without this a big enough number is a 500 — and "the
     * server broke" is not an answer to "that number is too large".
     */
    protected const DECIMAL_MAX = 99999999999;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The rules a `decimal(15,4)` quantity or money column needs.
     *
     * **`numeric` alone is not enough, and the gap is silent.** MySQL's strict mode
     * refuses too many integer digits but *rounds* extra decimal places, so `1.12345`
     * is accepted and stored as `1.1235`. Nothing errors, nothing is logged, and the
     * saved record quietly disagrees with what was typed — which for a per-unit BOM
     * quantity is then multiplied by every future production order. `decimal:0,4` is
     * the rule that refuses it instead.
     *
     * `0,4` rather than `4`: a range, so `2` and `2.5` are as acceptable as `2.5000`.
     * Requiring exactly four would mean typing three zeros to enter a whole number.
     *
     * The default bound is `gt:0`. A bill line for zero of something is not a line, and
     * a negative quantity is not a quantity. Callers that legitimately allow zero — a
     * stock count, later — pass `gte:0` instead.
     *
     * Mirrored in the browser by `decimal()` in resources/js/lib/validation/primitives.ts,
     * which checks the same four things in the same order and reports them with these
     * same messages.
     *
     * @return array<int, string>
     */
    protected function decimalRules(string $bound = 'gt:0'): array
    {
        return [
            'numeric',
            'decimal:0,'.self::DECIMAL_SCALE,
            $bound,
            'max:'.self::DECIMAL_MAX,
        ];
    }
}
