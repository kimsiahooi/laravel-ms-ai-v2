<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Support\ActiveExists;
use App\Support\StockItem;
use Closure;
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
     * A foreign key: an integer, naming a row that exists and is not trashed.
     *
     * **The `integer` is the load-bearing half, and its absence is silent.** Laravel's
     * `exists` accepts an *array* — it checks that every element exists — so
     * `warehouse_id[]=7` passes a bare `['required', ActiveExists::of('warehouses')]`.
     * The controller then reads it with `$request->integer()`, and PHP casts a non-empty
     * array to `1`. The request is validated, accepted, and applied to **row 1** — a
     * movement recorded against a warehouse nobody named. Nothing errors, nothing is
     * logged, and the ledger is wrong.
     *
     * That is the same shape as the `?search[]=x` bug that 500'd every list: a parameter
     * arriving as an array where a scalar was assumed. This is the version that corrupts
     * data rather than crashing, which is worse — so it lives in one helper rather than
     * in six rule arrays that each have to remember.
     *
     * `nullable` for an optional key stays the caller's to add, because it has to come
     * first.
     *
     * @return array<int, mixed>
     */
    protected function foreignKey(string $table): array
    {
        return ['integer', ActiveExists::of($table)];
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

    /**
     * A stock picker's value: one string naming a live product or raw material.
     *
     * A closure rather than `exists`, because *which table to look in is part of the
     * value* — see {@see StockItem::decode()}, which is the check as well as the
     * parser. It returns null for a wrong shape, an unknown type, an id that does not
     * exist and a row that has been soft-deleted, and all four deserve the same answer:
     * the thing you picked is not something you can pick.
     *
     * Here rather than in each request because there are four of them now — movements,
     * transfers, the on-hand lookup and reorder levels — and a copy that drifted would
     * mean one screen accepting an item another refuses.
     */
    protected function itemExists(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || StockItem::decode($value) === null) {
                $fail('validation.exists')->translate();
            }
        };
    }
}
