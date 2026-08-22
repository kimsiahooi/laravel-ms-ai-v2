<?php

declare(strict_types=1);

use App\Http\Controllers\Central\AdminSessionController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (landlord) routes
|--------------------------------------------------------------------------
|
| Only what lives outside a tenant workspace. Every application route is tenant-
| scoped and lives in routes/tenant.php, loaded by TenancyServiceProvider.
|
| 'admin' is a reserved slug (App\Support\ReservedSlugs), so /admin/* is never
| mistaken for a workspace: the global InitializeTenancyFromPath skips it and the
| {tenant} route pattern excludes it.
|
*/

Route::inertia('/', 'welcome')->name('home');

Route::prefix('admin')->name('admin.')->group(function (): void {
    // Bare /admin -> the dashboard when signed in, otherwise the login page.
    Route::get('/', fn () => redirect()->route(
        Auth::guard('central')->check() ? 'admin.dashboard' : 'admin.login'
    ))->name('home');

    Route::middleware('guest:central')->group(function (): void {
        Route::get('login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminSessionController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('login.store');
    });

    Route::middleware('auth:central')->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('tenants')->name('tenants.')->group(function (): void {
            Route::get('/', [TenantController::class, 'index'])->name('index');
            Route::post('/', [TenantController::class, 'store'])->name('store');
            Route::get('trashed', [TenantController::class, 'trashed'])->name('trashed');
            Route::delete('{tenant}', [TenantController::class, 'destroy'])->name('destroy');
            Route::patch('{tenant}/restore', [TenantController::class, 'restore'])
                ->withTrashed()
                ->name('restore');
            Route::delete('{tenant}/force', [TenantController::class, 'forceDestroy'])
                ->withTrashed()
                ->name('force-destroy');
        });

        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');
    });
});
