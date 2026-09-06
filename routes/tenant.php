<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\TableColumnController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\MediaController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\RawMaterialController;
use App\Http\Controllers\Tenant\StockLookupController;
use App\Http\Controllers\Tenant\StockMovementController;
use App\Http\Controllers\Tenant\StockTransferController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\WarehouseController;
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

            // Which columns this user looks at, per list. Deliberately unmapped in
            // TenantPermissions: it is a preference about the reader, not about a
            // resource, so every signed-in user may set their own. A separate route from
            // the central one for the reason the locale switcher gives above — the
            // session, and the user row this writes to, live in the tenant database.
            Route::put('table-columns', [TableColumnController::class, 'update'])
                ->name('tenant.table-columns.update');

            // Clear every list at once, from Settings -> Appearance. Same reasoning as
            // the PUT above: a preference about the reader, so no permission mapping.
            Route::delete('table-columns', [TableColumnController::class, 'destroy'])
                ->name('tenant.table-columns.destroy');

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

            Route::prefix('customers')->name('customers.')->group(function (): void {
                Route::get('/', [CustomerController::class, 'index'])->name('index');
                Route::post('/', [CustomerController::class, 'store'])->name('store');
                Route::patch('{customer}', [CustomerController::class, 'update'])->name('update');
                Route::delete('{customer}', [CustomerController::class, 'destroy'])->name('destroy');
            });

            // The URL segment is `raw-materials`; the route parameter is `rawMaterial`,
            // because that is the name Laravel resolves the model binding from.
            Route::prefix('raw-materials')->name('raw-materials.')->group(function (): void {
                Route::get('/', [RawMaterialController::class, 'index'])->name('index');
                Route::post('/', [RawMaterialController::class, 'store'])->name('store');
                Route::patch('{rawMaterial}', [RawMaterialController::class, 'update'])->name('update');
                Route::delete('{rawMaterial}', [RawMaterialController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('products')->name('products.')->group(function (): void {
                Route::get('/', [ProductController::class, 'index'])->name('index');
                Route::post('/', [ProductController::class, 'store'])->name('store');
                Route::patch('{product}', [ProductController::class, 'update'])->name('update');
                Route::delete('{product}', [ProductController::class, 'destroy'])->name('destroy');
                // PUT, not PATCH: the whole bill is replaced, never patched. The name
                // `products.bom` is what TenantPermissions maps to products.update.
                Route::put('{product}/bom', [ProductController::class, 'updateBom'])->name('bom');
            });

            // Stock. Sites first: a site owns warehouses and a warehouse holds the
            // stock, so nothing below can be addressed until this exists.
            Route::prefix('locations')->name('locations.')->group(function (): void {
                Route::get('/', [LocationController::class, 'index'])->name('index');
                Route::post('/', [LocationController::class, 'store'])->name('store');
                Route::patch('{location}', [LocationController::class, 'update'])->name('update');
                Route::delete('{location}', [LocationController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('warehouses')->name('warehouses.')->group(function (): void {
                Route::get('/', [WarehouseController::class, 'index'])->name('index');
                Route::post('/', [WarehouseController::class, 'store'])->name('store');
                Route::patch('{warehouse}', [WarehouseController::class, 'update'])->name('update');
                Route::delete('{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
            });

            // The ledger. No update and no delete: it is append-only, and a mistake is
            // corrected by recording the opposite movement — see the controller.
            Route::prefix('stock-movements')->name('stock-movements.')->group(function (): void {
                Route::get('/', [StockMovementController::class, 'index'])->name('index');
                Route::post('/', [StockMovementController::class, 'store'])->name('store');
            });

            // No update or delete, like the ledger: a transfer is a record of something
            // that happened, corrected by transferring back rather than by editing.
            Route::prefix('stock-transfers')->name('stock-transfers.')->group(function (): void {
                Route::get('/', [StockTransferController::class, 'index'])->name('index');
                Route::post('/', [StockTransferController::class, 'store'])->name('store');
            });

            // A read-only lookup the movement dialog makes while somebody is choosing,
            // so the quantity box is not typed into blind. JSON, not a page — see the
            // controller. Named `stock.on-hand` so TenantPermissions can map it.
            Route::get('stock/on-hand', [StockLookupController::class, 'onHand'])
                ->name('stock.on-hand');

            // Every uploaded file in the workspace, served from one place: a product
            // photo today, the business logo later. Deliberately outside the module
            // prefixes — media is addressed by its own id, not by what it hangs off, and
            // a per-module route would mean a new one for every collection.
            //
            // No permission is mapped for it in TenantPermissions. That is not the same
            // as open: MediaController reads the permission off the row's owner, which is
            // the only place that knows whether this particular file is a product photo
            // or next year's payroll scan.
            Route::get('media/{media}/{conversion?}', MediaController::class)->name('media');

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
