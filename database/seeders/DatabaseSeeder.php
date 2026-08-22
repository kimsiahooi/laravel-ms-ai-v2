<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The CENTRAL database seeder: a super-admin who can sign in at /admin and
 * provision tenants. Tenant databases are seeded by {@see TenantDatabaseSeeder}.
 *
 * firstOrCreate, so re-running never disturbs an existing admin's password.
 */
final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        CentralUser::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => 'password'],
        );
    }
}
