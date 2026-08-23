<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\OptionData;
use App\Data\ProductData;
use App\Enums\Unit;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products: list, create, edit, delete. The same shape as the rest of the catalog —
 * one screen, a dialog over the list, every write returning `back()`.
 *
 * Deleting is a soft delete. A sales order names the product it sold, and an order line
 * that cannot say what was sold is not a record of anything.
 */
final class ProductController
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
     * Category and supplier are absent: they live on other tables, and sorting by one
     * needs a join, which is not a controller's job. If it is ever wanted it belongs
     * behind a scope on the model.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'sku', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $products, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: Product::query()->with(['category', 'supplier', 'creator']),
            sortable: self::SORTABLE,
            toData: ProductData::fromProduct(...),
            searchUsing: self::searchBy(...),
        );

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => $filters,
            // The pickers' contents. Whole lists rather than a search endpoint: a
            // workspace has tens of categories and hundreds of suppliers at most, and
            // one query each here is cheaper than a round trip per keystroke there.
            // The combobox filters them in the browser.
            //
            // Trashed rows are excluded by the models' SoftDeletes scope, which is what
            // ActiveExists checks for on the way back in — a category nobody can pick
            // is also a category nobody can submit.
            'categories' => OptionData::collect(Category::query()->orderBy('name')->get()),
            'suppliers' => OptionData::collect(Supplier::query()->orderBy('name')->get()),
            'units' => Unit::grouped(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        $this->toast(__('products.toast.created', ['name' => $product->name]));

        return back();
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        $this->toast(__('products.toast.updated', ['name' => $product->name]));

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;

        $product->delete();

        $this->toast(__('products.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching products means. The columns live on the model — see
     * {@see Product::searchableColumns()}.
     *
     * @param  Builder<Product>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
