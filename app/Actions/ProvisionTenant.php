<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\ReservedSlugs;
use App\Support\TenantRoles;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Throwable;

/**
 * Creates a workspace: the `tenants` row, its own database (created and migrated
 * synchronously by the TenantCreated pipeline), the baseline tenant data, and the
 * first Administrator.
 *
 * Provisioning touches four things that can each fail independently, so the whole
 * of it is written to leave nothing behind when it does — see rollBack().
 */
final class ProvisionTenant
{
    public function handle(
        string $name,
        string $slug,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        string $locale = 'en',
    ): Tenant {
        // Checked before any database work: a reserved slug produces a workspace no
        // URL can reach. StoreTenantRequest rejects it first; this covers the action
        // being called from anywhere else (a command, a seeder).
        if (in_array($slug, ReservedSlugs::LIST, true)) {
            throw ValidationException::withMessages([
                'slug' => "The slug \"{$slug}\" is reserved and cannot be used.",
            ]);
        }

        try {
            // Central connection. The insert happens FIRST, then the `created` event
            // fires CreateDatabase + MigrateDatabase synchronously — so anything that
            // throws in here has already persisted the row.
            $tenant = Tenant::create([
                'id' => $slug, // the id column stores the slug (the tenant key)
                'name' => $name,
                'locale' => $locale,
            ]);
        } catch (Throwable $e) {
            // The database is only ours to drop when we are the ones who created it.
            // TenantDatabaseAlreadyExistsException means it was already there — very
            // possibly another application's — so it must be left strictly alone.
            $this->rollBack($slug, dropDatabase: ! $e instanceof TenantDatabaseAlreadyExistsException);

            throw $e;
        }

        try {
            // run() points the default connection at the tenant DB, runs the closure,
            // then reverts.
            $tenant->run(function () use ($adminName, $adminEmail, $adminPassword): void {
                // Baseline data first: the permission catalog and the Administrator
                // role have to exist before the role can be assigned below.
                app(TenantDatabaseSeeder::class)->run();

                // The password is hashed by the model's 'hashed' cast — do not pre-hash.
                User::create([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => $adminPassword,
                ])->assignRole(TenantRoles::ADMIN);
            });
        } catch (Throwable $e) {
            // The database exists and we made it, so it goes with the row. A tenant
            // with no user in it can never be signed into — half-provisioned is worse
            // than not provisioned, because the slug stays taken.
            $this->rollBack($slug, dropDatabase: true);

            throw $e;
        }

        return $tenant;
    }

    /**
     * Undo a failed provision.
     *
     * The database is dropped directly through the manager rather than by
     * force-deleting the model: forceDelete fires TenantDeleted, whose DeleteDatabase
     * job drops the database unconditionally — which is exactly wrong when the reason
     * we are here is that the database already belonged to someone else. So the row
     * is removed with events suppressed, and the drop is a separate, explicit,
     * existence-checked decision.
     *
     * Failures here are logged and swallowed: a rollback that throws would replace the
     * real cause with a second, less useful exception.
     */
    private function rollBack(string $slug, bool $dropDatabase): void
    {
        $tenant = Tenant::withTrashed()->find($slug);

        if ($tenant === null) {
            return;
        }

        if ($dropDatabase) {
            try {
                $manager = $tenant->database()->manager();
                $database = (string) $tenant->database()->getName();

                if ($manager->databaseExists($database)) {
                    $manager->deleteDatabase($tenant);
                }
            } catch (Throwable $e) {
                Log::warning('Failed to drop the database of a half-provisioned tenant.', [
                    'tenant' => $slug,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        try {
            Tenant::withoutEvents(static fn () => $tenant->forceDelete());
        } catch (Throwable $e) {
            Log::warning('Failed to remove the tenants row of a half-provisioned tenant.', [
                'tenant' => $slug,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
