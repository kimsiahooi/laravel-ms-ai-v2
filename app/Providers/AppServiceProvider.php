<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\StockTake;
use App\Models\StockTransfer;
use App\Support\ReservedSlugs;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureRouting();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        $this->configureDefaults();
    }

    /**
     * Constrain the {tenant} route parameter to a real slug.
     *
     * Without this, /admin/login is matched by Fortify's `{tenant}/login` route and
     * 404s as an unknown workspace instead of reaching the console's own sign-in.
     * The pattern excludes exactly the words in ReservedSlugs, the same list the
     * global InitializeTenancyFromPath middleware skips — one source, so routing and
     * tenancy resolution cannot drift apart.
     *
     * Declared in register(), NOT boot(), and that matters: a pattern is baked into
     * each route as it is defined, so it only applies to routes declared afterwards.
     * Fortify registers its {tenant}/… routes in its own boot(), which runs before
     * this provider's — from boot() the pattern would silently miss them, which is
     * precisely the bug this comment exists to prevent from coming back.
     */
    protected function configureRouting(): void
    {
        Route::pattern('tenant', ReservedSlugs::pattern());
    }

    /**
     * Short, stable keys in the `*_type` columns instead of class names.
     *
     * `stock_movements` and `warehouse_stocks` are the tables that need this. The ledger
     * is append-only and outlives any refactor, so storing `App\Models\Product` would
     * mean a data migration the day that class moves namespace — and every row of a
     * table nobody is allowed to rewrite. `product` costs less to store and cannot go
     * stale.
     *
     * Non-enforcing on purpose: the passkey and permission tables have morphs of their
     * own that the package writes, and enforcing would make those a runtime error.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap([
            'product' => Product::class,
            'raw_material' => RawMaterial::class,
            // What a ledger row points back at — see the `source` columns on
            // `stock_movements`. Same reasoning as the two above, and the same table:
            // these strings are written into rows nobody is allowed to rewrite.
            'purchase_order' => PurchaseOrder::class,
            'stock_take' => StockTake::class,
            'stock_transfer' => StockTransfer::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
