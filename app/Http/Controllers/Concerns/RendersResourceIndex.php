<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Turns a request and a query into the two props every server-paginated list sends:
 * a page of rows, and the `filters` the client echoes back.
 *
 * **The point of this trait is `filters`.** Its shape is a contract with
 * `ResourceFilters` in `types/ui.ts` and with `DataTable`, which re-sends every key on
 * every request. Assembled by hand in each controller it drifts one key at a time, and
 * a missing `sortable` is a header that looks clickable and silently does nothing.
 * Here it is built once.
 *
 * Three deliberate differences from the v1 trait this replaces:
 *
 *  - **It takes a `Builder`, not a `class-string`.** v1 called `$model::query()`,
 *    which cannot express `onlyTrashed()` — the archive listing could not use the
 *    helper at all and duplicated it instead. A Builder also means eager loading is
 *    plain `->with()` at the call site rather than another parameter, and PHPStan sees
 *    a concrete model instead of `Model`.
 *  - **It returns props; it does not render.** v1 rendered, so it needed `$view`,
 *    `$key` and an `$extraProps` array whose values are eagerly evaluated — a trap
 *    documented in v1 itself, because a partial reload asking only for the list still
 *    paid for every extra prop. Handing the props back means the controller writes an
 *    ordinary `Inertia::render`, where deferring and closures work as documented.
 *  - **Search is a closure, not a `search` scope called blind.** The trait resolves
 *    the term; what searching *means* stays visible at the call site.
 *
 * Requires {@see ResolvesPerPage} and {@see SortsResourceQuery}.
 */
trait RendersResourceIndex
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  already scoped — `onlyTrashed()`, `with()`, a tenant filter
     * @param  array<int, string>  $sortable  columns the UI may sort by; this list is the injection guard
     * @param  Closure(TModel): mixed  $toData  maps one row to its wire shape
     * @param  Closure(Builder<TModel>, string): void  $searchUsing  applied only when the term is non-empty
     * @param  string  $defaultSort  the house default, and lists are expected to take it:
     *                               newest first is the one ordering that means the same
     *                               thing on every screen, and it puts what someone just
     *                               created where they will look for it. Override only
     *                               where a list has its own notion of recency — the
     *                               workspace archive sorts by `deleted_at`, which is the
     *                               same rule applied to the event that list is about.
     * @return array{rows: LengthAwarePaginator<int, mixed>, filters: array{search: string, per_page: int, sort: string, direction: 'asc'|'desc', sortable: array<int, string>}}
     */
    protected function resourceList(
        Request $request,
        Builder $query,
        array $sortable,
        Closure $toData,
        Closure $searchUsing,
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): array {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        // The guard lives here rather than in each search closure: a closure that
        // forgot it would turn an empty box into `LIKE '%%'` on every column.
        if ($search !== '') {
            $searchUsing($query, $search);
        }

        $sort = $this->applySort($query, $request, $sortable, $defaultSort, $defaultDirection);

        return [
            'rows' => $this->paginateList($query, $perPage)->through($toData),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                ...$sort,
            ],
        ];
    }
}
