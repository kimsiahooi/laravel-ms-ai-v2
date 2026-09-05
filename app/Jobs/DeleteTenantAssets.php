<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Providers\TenancyServiceProvider;
use App\Support\Media\TenantPathGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a torn-down workspace's uploaded files — `storage/assets/{slug}/` — when the
 * workspace is force-deleted.
 *
 * It runs on the same pipeline as the database drop, and for the same reason: without
 * it, force-deleting a workspace reclaims the database and silently leaves every photo
 * anyone ever uploaded on disk, under a slug that can now be re-registered by somebody
 * else. One `deleteDirectory` reclaims the lot because
 * {@see TenantPathGenerator} put it all under one folder.
 *
 * Not a `ShouldQueue`. The pipeline in {@see TenancyServiceProvider} is
 * declared `shouldBeQueued(false)`, so this is called inline while the request that
 * pressed the button is still open — which is what makes a failure visible to the person
 * who pressed it rather than to a worker this app does not run.
 *
 * Only the slug is kept, never the model. By the time this runs the workspace has been
 * deleted, and a job holding a model that no longer has a row is a job that cannot be
 * retried or inspected.
 */
final class DeleteTenantAssets
{
    private readonly string $slug;

    public function __construct(Tenant $tenant)
    {
        $this->slug = (string) $tenant->getKey();
    }

    public function handle(): void
    {
        Storage::disk('assets')->deleteDirectory($this->slug);
    }
}
