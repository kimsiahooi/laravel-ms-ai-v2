<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\TenantPermissions;
use App\Support\TenantRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the built-in Administrator role into a tenant DB. It always holds every
 * permission (re-synced here so newly-added ones are picked up) and is locked in
 * the UI — it can't be edited or deleted, so a tenant can never lock itself out.
 * Runs after {@see PermissionsSeeder}, whose permissions it syncs.
 */
final class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::findOrCreate(TenantRoles::ADMIN, 'web');
        $admin->syncPermissions(TenantPermissions::names());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
