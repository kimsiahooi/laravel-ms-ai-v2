<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign-in for the central super-admin area. Deliberately hand-rolled rather than
 * Fortify's: Fortify is bound to the tenant `web` guard and lives under /{tenant},
 * and this is a small, separate door with no 2FA, passkeys or self-registration.
 */
final class AdminSessionController
{
    public function create(): Response
    {
        return Inertia::render('admin/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('central')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Always the admin dashboard — never intended(), whose stored URL may belong
        // to the tenant world and would misroute the super-admin into a workspace.
        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('central')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
