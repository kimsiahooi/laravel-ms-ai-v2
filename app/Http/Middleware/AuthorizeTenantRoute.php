<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the tenant permission catalog. Maps the current route to the permission
 * it requires ({@see TenantPermissions::routeMap()}) and 403s a signed-in user who
 * lacks it. Routes with no mapped permission (the dashboard, personal settings,
 * media, logout) stay open to any signed-in user.
 *
 * Applied to the tenant `auth:web` group, so a user is always present by the time
 * this runs — it decides what that user may do, never whether they are signed in.
 *
 * Route names here are bare (`categories.index`), not prefixed. Central routes are
 * all named `admin.*` and are served by a different group, so the two name spaces
 * cannot collide and no prefix stripping is needed.
 */
final class AuthorizeTenantRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $permissions = $this->permissionsFor($request);

        // `canAny`, so a route several screens share can name all of them and let any
        // one through — the on-hand lookup answers the same question for movements and
        // for transfers, and gating it on one would 403 a reader of the other.
        abort_if(
            $permissions !== [] && $request->user()?->canAny($permissions) !== true,
            403,
        );

        return $next($request);
    }

    /**
     * The permissions the current route accepts — any one of them is enough. Empty
     * means the route is open to any signed-in user.
     *
     * @return list<string>
     */
    private function permissionsFor(Request $request): array
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return [];
        }

        // Export downloads a resource's data, so gate it on that resource's view —
        // but only for a known resource. An unknown one has no such permission, so
        // let it fall through to the controller (a 404), not a 403.
        if ($name === 'export') {
            $permission = (string) $request->route('resource').'.view';

            return in_array($permission, TenantPermissions::names(), true)
                ? [$permission]
                : [];
        }

        return (array) (TenantPermissions::routeMap()[$name] ?? []);
    }
}
