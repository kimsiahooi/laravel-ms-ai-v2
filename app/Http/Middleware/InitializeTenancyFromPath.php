<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes tenancy from the first URL segment, GLOBALLY — before routing, and
 * therefore before StartSession.
 *
 * Why this exists instead of relying on stancl's route middleware
 * ---------------------------------------------------------------
 * StartSession builds the session store once, capturing BOTH the cookie name and
 * the database connection at that moment. Anything that changes those has to run
 * first. stancl's InitializeTenancyByPath is route middleware, and neither
 * middleware priority nor an explicitly ordered middleware group reordered it
 * before StartSession consistently for routes registered by a package (Fortify's).
 *
 * The bug that produced this class: signing in appeared to succeed — the POST
 * redirected to the dashboard — and the very next request was a guest again. The
 * authenticated session had been written to the CENTRAL database (the connection
 * StartSession captured) while every later read came from the tenant database. The
 * session cookie name was inconsistent between routes for the same reason.
 *
 * Global middleware run ahead of every route's stack, in a fixed order, so the
 * ordering cannot regress. The tenant is read from the URL's first segment because
 * routing has not happened yet; that is the same value stancl's route middleware
 * uses moments later, so the two cannot disagree.
 *
 * An unknown slug is deliberately NOT an error here — this middleware runs on every
 * request, including central ones. It simply does not initialize, and the route's
 * own InitializeTenancyByPath produces the 404.
 */
final class InitializeTenancyFromPath
{
    /**
     * First-segment paths that are NOT tenant slugs. Central areas keep the central
     * connection and the unscoped session cookie.
     */
    private const CENTRAL_SEGMENTS = [
        'admin',
        'up',
        'storage',
        'tenancy',
        '_inertia',
        'build',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);

        if ($segment === null || in_array($segment, self::CENTRAL_SEGMENTS, true)) {
            return $next($request);
        }

        $tenant = Tenant::find($segment);

        if ($tenant === null) {
            return $next($request);
        }

        // Gives each tenant its own cookie NAME, so one browser can hold a separate
        // signed-in session per tenant and visiting /b does not clobber /a's.
        // Set before initialize() so the bootstrappers and the session agree.
        config(['session.cookie' => config('session.cookie').'_tenant_'.$tenant->getTenantKey()]);

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
