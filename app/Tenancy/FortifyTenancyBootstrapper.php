<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Config\Repository;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Points Fortify's post-authentication redirect at the ACTIVE tenant's dashboard.
 *
 * Fortify resolves where to send a user after login/registration/password
 * confirmation through `Fortify::redirects()`, which reads `fortify.home` from
 * config and accepts no closure. A static `/dashboard` would drop every tenant user
 * onto a path that does not exist in a tenanted app.
 *
 * Rewriting the config value on tenancy init is the same technique
 * {@see SessionTenancyBootstrapper} uses for `session.cookie`, and it keeps the
 * redirect correct for every Fortify flow without reimplementing its response
 * classes.
 */
final class FortifyTenancyBootstrapper implements TenancyBootstrapper
{
    private ?string $originalHome = null;

    public function __construct(private readonly Repository $config) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalHome ??= $this->config->get('fortify.home');

        $this->config->set('fortify.home', '/'.$tenant->getTenantKey().'/dashboard');
    }

    public function revert(): void
    {
        if ($this->originalHome !== null) {
            $this->config->set('fortify.home', $this->originalHome);
        }
    }
}
