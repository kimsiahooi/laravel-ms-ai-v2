<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\CategoryData;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\CategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Product categories: list, create, edit, delete.
 *
 * The whole module is one screen — the form is a dialog over the list, and every
 * write returns `back()`, so the table refreshes in place and nobody loses their
 * search or their page.
 *
 * Deleting is a soft delete. Products filed under a category keep resolving their
 * link, which is the reason the row is kept rather than removed.
 */
final class CategoryController
{
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by. This list is the SQL-injection guard for
     * `?sort=` — see {@see SortsResourceQuery} — and it also decides which headers the
     * table renders as clickable.
     *
     * `creator` is absent on purpose: it is a name on a joined table, not a column
     * here, so ordering by it needs a join and joins belong in a Service.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $categories, 'filters' => $filters] = $this->resourceList(
            request: $request,
            // Eager-loaded: the "Created by" column reads it on every row, and without
            // this the list is one query per row.
            query: Category::query()->with('creator'),
            sortable: self::SORTABLE,
            toData: CategoryData::fromCategory(...),
            searchUsing: self::searchBy(...),
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return Inertia::render('categories/index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        $this->toast(__('categories.toast.created', ['name' => $category->name]));

        return back();
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        $this->toast(__('categories.toast.updated', ['name' => $category->name]));

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        $name = $category->name;

        $category->delete();

        $this->toast(__('categories.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching categories means. The columns live on the model — see
     * {@see Category::searchableColumns()} — because they describe the data, not
     * this request.
     *
     * @param  Builder<Category>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
