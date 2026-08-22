<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\SetTenantUrlDefault;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| Everything a tenant user can reach, under /{tenant}/… where {tenant} is the
| tenant's slug. InitializeTenancyByPath resolves that segment and repoints the
| default database connection at the tenant's own database; it is given the highest
| middleware priority (see TenancyServiceProvider) so it runs BEFORE StartSession.
|
| Fortify's own routes (login, password reset, 2FA, passkeys) are registered
| separately by the package under the same prefix and middleware — see
| config/fortify.php. They are not repeated here.
|
*/

Route::middleware(['web', InitializeTenancyByPath::class, SetTenantUrlDefault::class])
    ->prefix('{tenant}')
    ->group(function (): void {
        // Landing on the workspace root goes to the dashboard.
        Route::get('/', fn (string $tenant) => redirect()->route('dashboard', ['tenant' => $tenant]));

        Route::middleware(['auth:web'])->group(function (): void {
            Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

            Route::redirect('settings', 'settings/profile');
            Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

            Route::get('settings/security', [SecurityController::class, 'edit'])
                ->middleware(RequirePassword::class)
                ->name('security.edit');

            Route::put('settings/password', [SecurityController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('user-password.update');

            Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
        });
    });
