<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\ReservedSlugs;
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
 *
 * Why the session cookie is scoped by NAME *and* by PATH
 * ------------------------------------------------------
 * The name (`…_tenant_<slug>`) is what lets one browser hold a separate signed-in
 * session per workspace: /b's cookie has a different name, so it cannot clobber /a's.
 *
 * The path (`/<slug>`) is about a second cookie the framework writes: `XSRF-TOKEN`.
 * Its name is hard-coded in Laravel's CSRF middleware and cannot be scoped — but its
 * path comes from `session.path`, so that is the only lever available.
 *
 * The bug that produced the path scoping: sign in to the console, click through to a
 * workspace, come back, and archiving a workspace returned **419**. Both areas were
 * writing `XSRF-TOKEN` at path `/`, so the workspace's token overwrote the console's,
 * and the console page then sent a token belonging to a different session.
 *
 * With the path scoped, a console page sees only the `/`-path cookie (its own token),
 * and a workspace page sees both but the browser lists the longer path first
 * (RFC 6265 §5.4) — which is the one Inertia's cookie regex takes.
 */
final class InitializeTenancyFromPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $segment = $request->segment(1);

        // Reserved first segments are central areas (/admin, /up, /storage, …). They
        // keep the central connection and the unscoped session cookie. The same list
        // builds the {tenant} route pattern, so routing and this cannot disagree.
        if ($segment === null || in_array($segment, ReservedSlugs::LIST, true)) {
            return $next($request);
        }

        $tenant = Tenant::find($segment);

        if ($tenant === null) {
            return $next($request);
        }

        // Scope the session to this workspace by BOTH name and path. Set before
        // initialize() so the bootstrappers and the session agree. See the class
        // docblock for why each half is needed — neither is decorative.
        config([
            // `_ws_`, not the `_tenant_` this used to be. The cookie's shape changed
            // when the path was scoped, and a browser still holding the old path-`/`
            // copy would shadow the new one: the browser sends the shorter path last
            // and PHP keeps the LAST of two same-named cookies. Retiring the name
            // makes any stale copy unrecognised rather than authoritative.
            'session.cookie' => config('session.cookie').'_ws_'.$tenant->getTenantKey(),
            'session.path' => '/'.$tenant->getTenantKey(),
        ]);

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
