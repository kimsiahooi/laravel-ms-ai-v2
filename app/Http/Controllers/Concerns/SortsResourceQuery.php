<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The single sanctioning point for ORDER BY on a list.
 *
 * **This whitelist is a security control, not a convenience.** `orderBy()` does not
 * bind identifiers — the column name is interpolated into SQL — so the strict
 * `in_array` below is the only thing between `?sort=` and arbitrary SQL. A caller
 * that passes no allow-list gets an empty one, which is safe: everything falls back
 * to the default column. Never widen it with a value derived from the request.
 *
 * Two deliberate differences from the v1 trait this replaces:
 *
 *  - **`direction` is only honoured alongside a valid `sort`.** v1 read them
 *    independently, so `?direction=asc` on its own reordered the default column while
 *    the header UI — which keys its arrow off `sort` — could not show it. The table
 *    always sends the pair, so nothing legitimate relied on the split.
 *  - **The tiebreaker follows the primary direction.** v1 always appended `id desc`,
 *    giving an ascending sort a descending secondary key.
 */
trait SortsResourceQuery
{
    use ReadsQueryValues;

    /**
     * Apply a whitelisted sort and return the resolved pair to echo back in `filters`,
     * so the table can render its indicator on the column the server actually used.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $allowed
     * @return array{sort: string, direction: 'asc'|'desc', sortable: array<int, string>}
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $allowed,
        string $default = 'created_at',
        string $defaultDirection = 'desc',
    ): array {
        $sort = $this->requested($request, 'sort');

        $sorted = $sort !== null && in_array($sort, $allowed, true);
        $sort = $sorted ? $sort : $default;

        // Only a recognised column gets to choose its direction; see the class note.
        $direction = $sorted
            ? ($this->requested($request, 'direction') === 'asc' ? 'asc' : 'desc')
            : ($defaultDirection === 'asc' ? 'asc' : 'desc');

        $query->orderBy($sort, $direction);

        // A deterministic tiebreaker, or rows with equal values can repeat on one page
        // and vanish from another. Skipped when the key IS the sort.
        if ($sort !== $query->getModel()->getKeyName()) {
            $query->orderBy($query->getModel()->getKeyName(), $direction);
        }

        // The allow-list travels to the client so the table knows which headers are
        // clickable. One definition: a second copy in the page could disagree with the
        // one guarding the query, and a header would look sortable but do nothing.
        return ['sort' => $sort, 'direction' => $direction, 'sortable' => array_values($allowed)];
    }

    /**
     * A sort key or direction as it was asked for, lowercased, or null for neither.
     *
     * The array-shaped-URL guard lives in {@see ReadsQueryValues} now — it was found
     * here and needed everywhere.
     */
    private function requested(Request $request, string $key): ?string
    {
        $value = $this->queryValue($request, $key);

        return $value === '' ? null : strtolower($value);
    }
}
