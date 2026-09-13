<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds somebody on the security page until they replace the password an administrator typed
 * for them.
 *
 * **Why this exists at all.** Adding a colleague sets a password that person did not choose
 * and their administrator knows — see the `must_change_password` migration on why the app
 * takes that trade rather than emailing an invitation. The trade is only honest if the state
 * is temporary, and nothing but this makes it so: without it a "temporary" password is a
 * permanent one that two people know, and the second of them never agreed to it.
 *
 * **The exemption list is the whole design.** A middleware that redirects *everything* to the
 * security page redirects the security page too, and the only way out is a database edit. So
 * four things stay reachable, and each earns it:
 *
 * - `security.edit` — the page being sent to. Redirecting it to itself is an infinite loop.
 * - `user-password.update` — the form it posts to, which is the act that clears the flag.
 * - `logout` — somebody who cannot or will not set a password must still be able to leave.
 * - `tenant.locale.update` — the switcher is on every screen, and being held on a page in a
 *   language you cannot read is not a state to be stuck in.
 *
 * Everything else waits, including the dashboard. That is deliberate: a partial hold, where a
 * person can browse but not act, would leave the obligation indefinitely deferrable and the
 * password indefinitely shared.
 *
 * **Non-Inertia requests are let through untouched.** A redirect is only meaningful to
 * something that follows one; the JSON lookups this app makes would receive HTML.
 */
final class RequirePasswordChange
{
    /**
     * Routes a person under this hold may still reach. See the class note — every entry is
     * load-bearing, and removing one traps somebody.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'security.edit',
        'user-password.update',
        'logout',
        'tenant.locale.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($name !== null && in_array($name, self::ALLOWED, true)) {
            return $next($request);
        }

        // Only a request that can act on a redirect gets one. Inertia follows it; a `fetch`
        // for a stock level would render the security page into a JSON parse error.
        if (! $request->inertia() && ! $request->isMethod('GET')) {
            return $next($request);
        }

        return redirect()
            ->route('security.edit', ['tenant' => $request->route('tenant')])
            ->with('toast', ['type' => 'error', 'message' => __('users.error.must_change_password')]);
    }
}
