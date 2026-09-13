<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use App\Support\TableColumns;
use App\Support\TenantRoles;
use App\Support\TimeZones;
use Carbon\CarbonImmutable;
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
            // The locale the SERVER rendered with. The client loads the matching bundle
            // from this — never from navigator.language, which would render different
            // text on each side and produce a hydration mismatch.
            'locale' => fn (): string => app()->getLocale(),
            'locales' => fn (): array => Locales::options(),
            // The zone the SERVER formatted dates in, for the same reason as `locale`
            // directly above: the client must render the string the server already
            // rendered. Timestamps stay UTC everywhere else — this is display only.
            //
            // The workspace's own clock on a tenant page, because that is the calendar
            // the business reads on and a date should not change meaning with who opened
            // it. The browser's reported zone answers only on /admin, where there is no
            // workspace. See TimeZones::resolve().
            'timezone' => TimeZones::resolve($request),
            // What day it is where the reader is, as `Y-m-d`.
            //
            // A prop rather than a `new Date()` in the browser, for the reason the two
            // above give and one more that is specific to it: a calendar marks today,
            // and reading the clock during render is the one thing `scripts/ui-guard.sh`
            // refuses outright — the server and the browser can land either side of
            // midnight and the mismatch is a React #418 nothing here would catch.
            //
            // Resolved in the same zone everything else on the page renders in — the
            // workspace's — so a calendar marks the business's today.
            // See resources/js/components/form/date-field.tsx.
            'today' => fn (): string => CarbonImmutable::now(TimeZones::resolve($request))->format('Y-m-d'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Which columns this person looks at, per list — see App\Support\TableColumns.
            // Resolved through the same guard expression as `auth.user` above and for the
            // same reason: share() runs before route middleware, so `auth:central` has not
            // switched the default guard yet and $request->user() alone is null on /admin.
            //
            // It has to be a prop rather than anything the browser reads for itself. The
            // table seeds its state from this during render, so the server and the client
            // must be looking at the same value or the first paint disagrees.
            'tableColumns' => TableColumns::forUser(
                $this->isAdminArea($request) ? $request->user('central') : $webUser,
            ),
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
