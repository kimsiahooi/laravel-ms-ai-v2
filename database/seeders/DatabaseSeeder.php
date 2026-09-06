<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Console\Commands\CreateAdminCommand;
use App\Models\CentralUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The CENTRAL database seeder: a super-admin who can sign in at /admin and
 * provision tenants. Tenant databases are seeded by {@see TenantDatabaseSeeder}.
 *
 * firstOrCreate, so re-running never disturbs an existing admin's password.
 *
 * **It refuses to run in production, deliberately.** The account below has a password
 * everybody reading this repository knows. `AppServiceProvider` applies the strong
 * password rules only to a password somebody *types*, so this one would still sign in —
 * a seeded back door that nothing would ever warn about.
 *
 * Taking the credentials from the environment instead was the obvious alternative and is
 * a trap: `php artisan optimize` caches the config, after which `env()` outside a config
 * file returns null, so the guard would quietly stop guarding on exactly the machine it
 * exists for. Production admins are made with `php artisan admin:create` instead — see
 * {@see CreateAdminCommand}.
 */
final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Refusing to seed the central database in production: this seeder creates '
                .'a super-admin whose password is public knowledge. Run `php artisan '
                .'admin:create` instead.',
            );
        }

        CentralUser::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => 'password'],
        );
    }
}
