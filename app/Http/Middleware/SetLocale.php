<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the UI language for this request, first match wins:
 *
 *   1. an explicit choice made with the language switcher (this session)
 *   2. the signed-in tenant user's own preference
 *   3. the workspace's default
 *   4. config('app.locale')
 *
 * The session comes first because the switcher has to work in places where there is
 * no user record to write to — the sign-in screens, and the whole console, whose
 * CentralUser carries no locale.
 *
 * Deliberately NOT the Accept-Language header or `navigator.language`: the client has
 * to render with the same locale the server did, and anything the browser decides for
 * itself is a hydration mismatch waiting to happen. HandleInertiaRequests shares the
 * resolved value so the client loads that exact bundle.
 *
 * Runs inside the `web` group, after StartSession — it needs the session to know who
 * is signed in — and before HandleInertiaRequests, which reads the result.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    /** Session key the language switcher writes to. */
    public const SESSION_KEY = 'locale';

    private function resolve(Request $request): ?string
    {
        $chosen = $request->session()->get(self::SESSION_KEY);

        if (is_string($chosen) && Locales::supports($chosen)) {
            return $chosen;
        }

        $user = $request->user();

        // Only the tenant User carries a preference; a central super-admin has no
        // locale column, and falls through to the app default.
        if ($user instanceof User && Locales::supports($user->locale)) {
            return $user->locale;
        }

        $tenant = tenant();

        if ($tenant !== null && Locales::supports($tenant->locale)) {
            return $tenant->locale;
        }

        return null;
    }
}
