<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\ReplaceBom;
use App\Data\OptionData;
use App\Data\ProductData;
use App\Enums\Unit;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\BomRequest;
use App\Http\Requests\Tenant\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\Supplier;
use App\Support\TenantPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    /**
     * Validated input that is not a column. They describe what should happen to the
     * photo, and passing them to `create()`/`update()` would be asking Eloquent to write
     * an uploaded file into a field that does not exist.
     *
     * @var array<int, string>
     */
    private const IMAGE_FIELDS = ['image', 'remove_image'];

    public function index(Request $request): Response
    {
        ['rows' => $products, 'filters' => $filters] = $this->resourceList(
            request: $request,
            // `media` and `bomItems` with the rest: without them every row asks for
            // its own photo and its own bill, and a page of twenty-five products is
            // fifty-one queries instead of three.
            query: Product::query()->with([
                'category', 'supplier', 'creator', 'media', 'bomItems.rawMaterial',
            ]),
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
            // The bill editor's picker. By name, because that is what somebody is
            // scanning the list for — the id order the table uses would put the
            // material added last at the bottom of a hundred-row popover.
            'rawMaterials' => OptionData::collect(RawMaterial::query()->orderBy('name')->get()),
            'units' => Unit::grouped(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->safe()->except(self::IMAGE_FIELDS));

        $this->syncImage($request, $product);

        $this->toast(__('products.toast.created', ['name' => $product->name]));

        return back();
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->safe()->except(self::IMAGE_FIELDS));

        $this->syncImage($request, $product);

        $this->toast(__('products.toast.updated', ['name' => $product->name]));

        return back();
    }

    /**
     * Replace the product's bill of materials.
     *
     * Its own route rather than part of `update()`, because it is its own kind of edit:
     * a variable number of lines rather than a fixed set of fields, saved from its own
     * dialog, and gated by the same permission through
     * {@see TenantPermissions} ROUTE_OVERRIDES.
     */
    public function updateBom(BomRequest $request, Product $product, ReplaceBom $replace): RedirectResponse
    {
        // An empty bill sends no `items` inputs at all, so the key is absent from the
        // payload — see BomRequest on why that is the same thing as an empty list.
        $replace->handle($product, $request->lines());

        $this->toast(__('products.toast.bom_saved', ['name' => $product->name]));

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;

        // The photo stays on disk. medialibrary only removes files on a force delete, so
        // a product that is restored comes back with its picture; DeleteTenantAssets is
        // what eventually reclaims them, when the whole workspace goes.
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

    /**
     * Apply whatever the form said about the photo. Nothing, usually.
     *
     * A new file wins over the Remove flag, and the early return is what says so: if
     * somebody presses Remove and then picks a replacement before saving, they meant the
     * replacement. Handling them in the other order would delete the file they just chose.
     *
     * There is no third case. The collection is `singleFile()`, so adding *is* replacing
     * — the previous row and its file are removed by medialibrary, not by us.
     */
    private function syncImage(ProductRequest $request, Product $product): void
    {
        $file = $request->file('image');

        if ($file instanceof UploadedFile) {
            $product->addMedia($file)->toMediaCollection(Product::IMAGE);

            return;
        }

        if ($request->boolean('remove_image')) {
            $product->clearMediaCollection(Product::IMAGE);
        }
    }
}
