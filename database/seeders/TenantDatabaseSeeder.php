<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\ProvisionTenant;
use Illuminate\Database\Seeder;

/**
 * The root seeder for a TENANT database — the single entry point for the baseline
 * data every tenant needs. Runs on provision ({@see ProvisionTenant}) and via
 * `php artisan tenants:seed` (config/tenancy.php → seeder_parameters points here).
 *
 * Every sub-seeder is additive and idempotent, so re-running it across existing
 * tenants is how new baseline data is rolled out.
 *
 * NOT the central DatabaseSeeder, which seeds the super-admin into the central
 * database instead.
 */
final class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            BusinessSettingsSeeder::class,
        ]);
    }
}
