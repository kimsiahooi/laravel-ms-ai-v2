<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InitializeTenancyFromPath;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Runs before routing — and therefore before StartSession, which captures
        // the session's cookie name AND database connection when it builds the
        // store. See the class docblock for the bug this prevents.
        $middleware->prepend(InitializeTenancyFromPath::class);

        $middleware->web(append: [
            HandleAppearance::class,
            // Before HandleInertiaRequests, which shares the locale it resolves.
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Fortify's routes live under /{tenant}/, so a guest redirect has to carry
        // the slug. Without this Laravel would aim at a bare /login, which is not a
        // route in this app.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            $tenant = tenant();

            return $tenant !== null
                ? route('login', ['tenant' => $tenant->getTenantKey()])
                : route('home');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.dashboard');
            }

            $tenant = tenant();

            return $tenant !== null
                ? route('dashboard', ['tenant' => $tenant->getTenantKey()])
                : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An unknown slug in the URL is a wrong address, not a server fault.
        // Without this, stancl's identification failure surfaces as a 500.
        $exceptions->render(function (TenantCouldNotBeIdentifiedException $e): void {
            abort(404);
        });
    })->create();
