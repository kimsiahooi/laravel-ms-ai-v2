<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\ProvisionTenant;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace management for the super-admin: create, list, archive, restore and
 * permanently delete.
 *
 * Archiving is a soft delete and leaves the tenant's database untouched — only
 * force-delete drops it (see the $dispatchesEvents remap on {@see Tenant}).
 */
final class TenantController
{
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a workspace listing may be ordered by. This list is the SQL-injection
     * guard for `?sort=` — see {@see SortsResourceQuery}. `id` is the slug.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'id', 'created_at'];

    /** Archived listings sort by when they were archived instead of when created. */
    private const SORTABLE_TRASHED = ['name', 'id', 'deleted_at'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $query = Tenant::query()->tap($this->search($search));
        $sort = $this->applySort($query, $request, self::SORTABLE);

        $tenants = $this->paginateList($query, $perPage)
            ->through(fn (Tenant $tenant): array => [
                'slug' => $tenant->getKey(),
                'name' => $tenant->name,
                'created_at' => $tenant->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => [...$sort, 'search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function trashed(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $query = Tenant::onlyTrashed()->tap($this->search($search));
        $sort = $this->applySort($query, $request, self::SORTABLE_TRASHED, 'deleted_at');

        $tenants = $this->paginateList($query, $perPage)
            ->through(fn (Tenant $tenant): array => [
                'slug' => $tenant->getKey(),
                'name' => $tenant->name,
                'deleted_at' => $tenant->deleted_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/tenants/trashed', [
            'tenants' => $tenants,
            'filters' => [...$sort, 'search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreTenantRequest $request, ProvisionTenant $provision): RedirectResponse
    {
        $data = $request->validated();

        $tenant = $provision->handle(
            name: $data['name'],
            slug: $data['slug'],
            adminName: $data['admin_name'],
            adminEmail: $data['admin_email'],
            adminPassword: $data['admin_password'],
        );

        $this->toast(__('console.toast.created', ['name' => $tenant->name, 'slug' => $tenant->getKey()]));

        return redirect()->route('admin.tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        $this->toast(__('console.toast.archived', ['name' => $tenant->name]));

        return back();
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $tenant->restore();

        $this->toast(__('console.toast.restored', ['name' => $tenant->name]));

        return back();
    }

    public function forceDestroy(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $name = $tenant->name;

        // Fires TenantDeleted -> DeleteDatabase. The workspace's database and every
        // row in it go with it; there is no undo.
        $tenant->forceDelete();

        $this->toast(__('console.toast.deleted', ['name' => $name]));

        return back();
    }

    /**
     * Name-or-slug search, as a tap() so both listings share one definition.
     *
     * @return Closure(Builder<Tenant>): void
     */
    private function search(string $search): Closure
    {
        return function (Builder $query) use ($search): void {
            if ($search === '') {
                return;
            }

            $query->where(function (Builder $group) use ($search): void {
                $group->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        };
    }
}
