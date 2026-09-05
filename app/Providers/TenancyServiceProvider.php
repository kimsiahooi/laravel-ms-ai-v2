<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\DeleteTenantAssets;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

/**
 * Wires stancl/tenancy for this app: multi-database, tenants identified by the
 * `/{slug}/…` path segment.
 *
 * Trimmed from the package stub to only the events this app uses — domain and
 * impersonation events are omitted because neither feature is enabled.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, array<int, mixed>>
     */
    public function events(): array
    {
        return [
            // Creating a tenant creates and migrates its database, synchronously,
            // before Tenant::create() returns.
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                ])->send(fn (Events\TenantCreated $event) => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            // Fired only on FORCE delete — see the $dispatchesEvents remap on the
            // Tenant model. A soft delete must NOT drop the database.
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                    // The database first: if dropping it fails the pipeline stops, and
                    // the workspace's files are still there to go with the data that
                    // survived. The other order loses the photos either way.
                    DeleteTenantAssets::class,
                ])->send(fn (Events\TenantDeleted $event) => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],
        ];
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function (): void {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::group([], base_path('routes/tenant.php'));
            }
        });
    }

    // Middleware priority (tenancy before StartSession) is registered in
    // bootstrap/app.php — doing it here via the Kernel runs too late in Laravel 13
    // and silently leaves StartSession first.
}
