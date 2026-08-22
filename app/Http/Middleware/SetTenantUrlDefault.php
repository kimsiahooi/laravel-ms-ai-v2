<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registers the active tenant as the default `{tenant}` route parameter.
 *
 * Every application route is prefixed `/{tenant}/`, so without a default, every
 * single `route()` call — server side and in the generated TypeScript helpers —
 * would have to pass the slug by hand. That is hundreds of call sites carrying a
 * value that is already unambiguous from the URL.
 *
 * It also drives Wayfinder: its generator scans ROUTE middleware for a literal
 * `URL::defaults` call and, finding one, emits `{tenant}` as an OPTIONAL argument
 * in the TypeScript route helpers. So `dashboard()` type-checks and resolves to the
 * current tenant, while `dashboard({ tenant: 'other' })` still works for the rare
 * cross-tenant link. This must stay ROUTE middleware (not global) for the generator
 * to see it.
 */
final class SetTenantUrlDefault
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant !== null) {
            URL::defaults(['tenant' => $tenant->getTenantKey()]);
        }

        return $next($request);
    }
}
