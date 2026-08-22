<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Resolves a validated `per_page` for a paginated index and applies the shared
 * pagination defaults. Every list controller uses this so the allow-list, the
 * fallback and the query-string handling stay identical across the app.
 *
 * A controller can override $perPageOptions if it needs a different allow-list.
 */
trait ResolvesPerPage
{
    /** @var array<int, int> */
    protected array $perPageOptions = [10, 25, 50, 100];

    protected function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', $this->perPageOptions[0]);

        return in_array($perPage, $this->perPageOptions, true)
            ? $perPage
            : $this->perPageOptions[0];
    }

    /**
     * Paginate a list query, preserving the current query string on the page links
     * so a search or a per-page choice survives paging. Ordering stays with the
     * caller — add a deterministic one there, or rows can repeat across pages.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateList(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString();
    }
}
