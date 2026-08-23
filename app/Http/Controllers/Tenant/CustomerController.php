<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\CustomerData;
use App\Enums\Country;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\CustomerRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers: list, create, edit, delete. Same shape as categories and suppliers — one
 * screen, a dialog over the list, every write returning `back()`.
 *
 * Deleting is a soft delete. Sales orders and invoices will reference customers, and an
 * invoice that cannot name who it was issued to is not a record of anything.
 */
final class CustomerController
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
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        ['rows' => $customers, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: Customer::query()->with('creator'),
            sortable: self::SORTABLE,
            toData: CustomerData::fromCustomer(...),
            searchUsing: self::searchBy(...),
        );

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $filters,
            // Codes only. The names are user-facing strings and live in
            // lang/{locale}/countries.php, keyed by these — sending English labels
            // would ship one language's words inside the data.
            'countries' => Country::codes(),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->validated());

        $this->toast(__('customers.toast.created', ['name' => $customer->name]));

        return back();
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        $this->toast(__('customers.toast.updated', ['name' => $customer->name]));

        return back();
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $name = $customer->name;

        $customer->delete();

        $this->toast(__('customers.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * What searching customers means. The columns live on the model — see
     * {@see Customer::searchableColumns()}.
     *
     * @param  Builder<Customer>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $query->search($term);
    }
}
