<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches the UI language.
 *
 * Stored in the session so it works everywhere — including the sign-in screens and
 * the console, neither of which has a user record to write a preference to. A
 * signed-in tenant user also gets it saved to their profile, so the choice survives
 * signing out.
 *
 * Central (not tenant-prefixed) so one route serves both areas; `locale` is a
 * reserved slug, so it can never be mistaken for a workspace.
 */
final class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::codes())],
        ]);

        $request->session()->put(SetLocale::SESSION_KEY, $validated['locale']);

        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['locale' => $validated['locale']])->save();
        }

        return back();
    }
}
