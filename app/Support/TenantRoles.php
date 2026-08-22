<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The built-in tenant roles. Only one so far: every tenant gets an Administrator
 * holding the whole permission catalog, assigned to the first user at provision
 * time. It is re-synced by the seeder and locked in the UI, so a tenant can never
 * edit or delete its way out of its own workspace.
 *
 * Named here rather than on the seeder because the middleware and the Inertia
 * share need it too, and none of them should have to reach into `Database\Seeders`.
 */
final class TenantRoles
{
    public const ADMIN = 'Administrator';
}
