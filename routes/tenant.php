<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Middleware\AuthorizeTenantRoute;
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
        //
        // No `$tenant` argument, deliberately: stancl's PathTenantResolver calls
        // $route->forgetParameter('tenant') once it has identified the workspace, so a
        // route action can never receive it — asking for one is a 500, not a slug. The
        // slug is filled in by SetTenantUrlDefault's URL::defaults, which is how every
        // other route() call in the app resolves it too.
        Route::get('/', fn () => redirect()->route('dashboard'));

        // The language switcher, again.
        //
        // There is an identical central route (routes/web.php), and a workspace cannot
        // use it: the session driver is `database` and DatabaseTenancyBootstrapper
        // switches the connection, so a workspace's session row lives in the tenant
        // database while the central route reads `central.sessions`. Posting there from
        // inside a workspace finds no session, starts a fresh one, and the CSRF check
        // rejects it as a 419.
        //
        // Outside the auth group on purpose: the sign-in screen offers the switcher too,
        // and someone who cannot read the form yet is exactly who needs it.
        Route::put('locale', LocaleController::class)->name('tenant.locale.update');

        // AuthorizeTenantRoute maps each route name to the permission it needs
        // (App\Support\TenantPermissions) and 403s a user who lacks it. Routes with
        // no mapped permission stay open to any signed-in user.
        Route::middleware(['auth:web', AuthorizeTenantRoute::class])->group(function (): void {
            Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

            // Catalog. Route names are bare — `categories.index`, not `tenant.categories.index`
            // — because that is the shape TenantPermissions::routeMap() keys on.
            //
            // No `show`: the module is a single screen and the form is a dialog over the
            // list, so there is nothing a detail page would add.
            Route::prefix('categories')->name('categories.')->group(function (): void {
                Route::get('/', [CategoryController::class, 'index'])->name('index');
                Route::post('/', [CategoryController::class, 'store'])->name('store');
                Route::patch('{category}', [CategoryController::class, 'update'])->name('update');
                Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('suppliers')->name('suppliers.')->group(function (): void {
                Route::get('/', [SupplierController::class, 'index'])->name('index');
                Route::post('/', [SupplierController::class, 'store'])->name('store');
                Route::patch('{supplier}', [SupplierController::class, 'update'])->name('update');
                Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
            });

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
