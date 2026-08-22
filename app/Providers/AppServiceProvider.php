<?php

namespace App\Providers;

use App\Support\ReservedSlugs;
use Carbon\CarbonImmutable;
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
