<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = tenant();

        // The signed-in TENANT user (default `web` guard). Null on /admin pages, which
        // authenticate a CentralUser on the `central` guard instead.
        $webUser = $request->user();

        // Permissions live in the tenant database and only mean anything inside a
        // workspace, so only look them up there — a central or guest page has none, and
        // the central database has no permission tables to query.
        $tenantUser = $tenant !== null && $webUser instanceof User ? $webUser : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                // Resolve the right guard for the area: /admin authenticates
                // super-admins, everything else the tenant user.
                'user' => $this->isAdminArea($request) ? $request->user('central') : $webUser,
                // What the signed-in tenant user may do, so the UI can hide what they
                // can't. Convenience only — AuthorizeTenantRoute is the boundary.
                // Deferred behind closures so a partial reload that doesn't ask for
                // them skips the tenant-DB lookup entirely.
                'permissions' => fn (): array => $tenantUser?->getAllPermissions()->pluck('name')->all() ?? [],
                'is_admin' => fn (): bool => $tenantUser?->hasRole(TenantRoles::ADMIN) ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Identifies the current workspace. The client registers `slug` as the
            // default {tenant} route parameter (see app.tsx), so route helpers
            // resolve to this workspace without every call site passing it.
            'tenant' => $tenant === null ? null : [
                'slug' => $tenant->getTenantKey(),
                'name' => $tenant->name,
            ],
        ];
    }

    private function isAdminArea(Request $request): bool
    {
        return $request->is('admin', 'admin/*');
    }
}
