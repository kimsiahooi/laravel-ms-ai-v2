<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\TableColumnController;
use App\Http\Controllers\Tenant\BusinessSettingsController;
use App\Http\Controllers\Tenant\CategoryController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\MediaController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\PurchaseOrderController;
use App\Http\Controllers\Tenant\RawMaterialController;
use App\Http\Controllers\Tenant\StockLookupController;
use App\Http\Controllers\Tenant\StockMovementController;
use App\Http\Controllers\Tenant\StockTakeController;
use App\Http\Controllers\Tenant\StockTransferController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\WarehouseController;
use App\Http\Controllers\Tenant\WarehouseReorderLevelController;
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

                // The first `show` in the app, and the first screen that is a position
                // rather than a record: what this warehouse holds, and when each item
                // wants restocking. `warehouses.show` maps to `warehouses.view` without
                // an override — TenantPermissions gives every screen's show route the
                // screen's own view permission.
                Route::get('{warehouse}', [WarehouseController::class, 'show'])->name('show');

                // The only write the detail screen makes. PUT rather than PATCH: there
                // is one field, and setting it replaces whatever was there — including
                // replacing it with nothing, which is how a level is cleared.
                Route::put('{warehouse}/reorder-levels', [WarehouseReorderLevelController::class, 'update'])
                    ->name('reorder-levels.update');

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

            // The only stock module with a lifecycle. A count is a draft document that
            // is filled in over hours and then applied once, so it needs a detail screen
            // and five writes where the ledger needed one.
            //
            // Every name here is load-bearing: TenantPermissions maps `count` and
            // `lines` to `stock-takes.create` by override, and AuthorizeTenantRoute
            // treats a route it cannot find in that map as open to any signed-in user.
            // A renamed route is a silently unguarded one.
            //
            // The parameter is `{stockTake}` rather than `{stock_take}` because that is
            // the name Laravel resolves the model binding from — and the name the count
            // request's `line` rule reaches back through to scope a line to its own take.
            //
            // No update and no line delete. A count is corrected by counting again, and
            // an added line left uncounted is inert at posting — see the controller.
            Route::prefix('stock-takes')->name('stock-takes.')->group(function (): void {
                Route::get('/', [StockTakeController::class, 'index'])->name('index');
                Route::post('/', [StockTakeController::class, 'store'])->name('store');
                Route::get('{stockTake}', [StockTakeController::class, 'show'])->name('show');

                // POST, not PATCH, and once per line: the sheet saves each number as it
                // is entered rather than holding the whole count in the browser until a
                // submit that a closed tab would lose.
                Route::post('{stockTake}/count', [StockTakeController::class, 'count'])->name('count');
                Route::post('{stockTake}/lines', [StockTakeController::class, 'addLine'])->name('lines');
                Route::post('{stockTake}/post', [StockTakeController::class, 'post'])->name('post');
                Route::post('{stockTake}/cancel', [StockTakeController::class, 'cancel'])->name('cancel');
                Route::delete('{stockTake}', [StockTakeController::class, 'destroy'])->name('destroy');
            });

            // The first module with form *pages* rather than a dialog over a list: an
            // order is a header and a grid of priced lines, which is not something a
            // modal holds. So `create` and `edit` are real GET routes here.
            //
            // **`create` is declared before `{purchaseOrder}`, and the order matters.**
            // Laravel matches in declaration order, so the reverse would send /create to
            // `show` and 404 looking for an order numbered "create".
            //
            // Every name here is load-bearing: TenantPermissions maps `receive` and
            // `cancel` to `purchase-orders.update` by override, and `create` and `edit`
            // by override too — without those two, AuthorizeTenantRoute would find no
            // entry for the form pages and treat them as open to any signed-in user.
            //
            // The parameter is `{purchaseOrder}` rather than `{purchase_order}`, because
            // that is the name Laravel resolves the model binding from.
            //
            // Delete is a soft delete, and only a pending order may be deleted at all —
            // a received one is named as the source of every ledger row it wrote. See
            // the controller.
            Route::prefix('purchase-orders')->name('purchase-orders.')->group(function (): void {
                Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
                Route::get('create', [PurchaseOrderController::class, 'create'])->name('create');
                Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
                Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
                Route::get('{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');

                // PATCH, though the whole order arrives: the lines are replaced wholesale
                // but the document is not — its number, its status and its receipt
                // columns are untouchable from here, so this is a partial update of the
                // row however complete the form is.
                Route::patch('{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');

                // The two transitions, both out of pending and both terminal.
                Route::post('{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
                Route::post('{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
                Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
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

            // The workspace's own settings, filed under the same URL prefix as the
            // account ones because that is where a person looks for "settings" — but
            // they are a different kind of thing: these belong to the business and are
            // permission-gated, while profile, security and appearance belong to
            // whoever is signed in and are open to everyone.
            //
            // **The names are fixed, not chosen.** TenantPermissions maps
            // `settings.index` to `settings.view` and `settings.update` to
            // `settings.update`, and that catalog is already seeded in every workspace.
            // Anything else here is a route AuthorizeTenantRoute cannot find, which it
            // treats as open to any signed-in user — so a nicer name would silently
            // hand the tax rate to everybody.
            Route::get('settings/business', [BusinessSettingsController::class, 'index'])
                ->name('settings.index');

            // PUT, not PATCH: the form carries every field and replaces the row whole.
            // A partial update has no meaning for a single row of settings that are
            // read together.
            Route::put('settings/business', [BusinessSettingsController::class, 'update'])
                ->name('settings.update');
        });
    });
