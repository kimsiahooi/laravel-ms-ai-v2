<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Actions\ProvisionTenant;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
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

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $tenants = $this->paginateList(
            Tenant::query()->tap($this->search($search))->latest()->latest('id'),
            $perPage,
        )->through(fn (Tenant $tenant): array => [
            'slug' => $tenant->getKey(),
            'name' => $tenant->name,
            'created_at' => $tenant->created_at->toIso8601String(),
        ]);

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function trashed(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = $this->perPage($request);

        $tenants = $this->paginateList(
            Tenant::onlyTrashed()->tap($this->search($search))->orderByDesc('deleted_at')->latest('id'),
            $perPage,
        )->through(fn (Tenant $tenant): array => [
            'slug' => $tenant->getKey(),
            'name' => $tenant->name,
            'deleted_at' => $tenant->deleted_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/tenants/trashed', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'per_page' => $perPage],
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

        $this->toast("Workspace \"{$tenant->name}\" created — sign in at /{$tenant->getKey()}/login.");

        return redirect()->route('admin.tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        $this->toast("Workspace \"{$tenant->name}\" archived. Its data is untouched.");

        return back();
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $tenant->restore();

        $this->toast("Workspace \"{$tenant->name}\" restored.");

        return back();
    }

    public function forceDestroy(Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->trashed(), 404);

        $name = $tenant->name;

        // Fires TenantDeleted -> DeleteDatabase. The workspace's database and every
        // row in it go with it; there is no undo.
        $tenant->forceDelete();

        $this->toast("Workspace \"{$name}\" and its database were permanently deleted.");

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
