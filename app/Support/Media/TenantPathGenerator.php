<?php

declare(strict_types=1);

namespace App\Support\Media;

use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Where a file is written on the shared `assets` disk:
 *
 *     {workspace}/products/{ulid}.jpg
 *     {workspace}/products/conversions/{ulid}-thumb.jpg
 *
 * Two segments, each earning its place.
 *
 * **The workspace slug** is not decoration. Every workspace has its own database, so media
 * ids start at 1 in each of them; without the prefix, workspace A's files and workspace B's
 * files are the same tree. It is also the whole of a workspace's uploads in one folder,
 * which is what lets DeleteTenantAssets reclaim them in a single call.
 *
 * **The owner's folder** says what the file is without a database round trip. `products/`
 * beats a bare numbered directory when the question is "what is this file and who has it",
 * which is the question anyone looking at a disk is actually asking. It comes from
 * {@see MediaOwners}, which refuses an unregistered model rather than inventing a folder.
 *
 * The package's own generator puts every file in its own `{media-id}/` directory, and
 * dropping that is a deliberate trade, not an oversight:
 *
 * - **Uniqueness** moves from the directory to the filename. {@see UlidFileNamer} gives
 *   each file a 26-character identifier, so two uploads of `photo.jpg` become two different
 *   names rather than two files in different folders. Nothing can overwrite anything.
 * - **Deleting one file costs a directory listing.** medialibrary's DefaultFileRemover
 *   calls `allFiles()` on this directory and deletes only the entry matching the media's
 *   own `file_name` — targeted, never a blanket wipe, so a flat folder is safe. But that
 *   listing is now over every file in `products/` rather than over one. On a local disk
 *   that is a readdir; on S3 it would be a paginated LIST per delete. Worth knowing before
 *   this disk ever moves to object storage.
 *
 * Conversions sit in a sibling folder rather than beside the originals so that listing
 * `products/` shows one entry per product instead of alternating originals and thumbnails.
 * They share the original's ULID stem, so a thumbnail is still trivially traced back.
 */
final class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->tenantPrefix().MediaOwners::folder($media->model_type).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }

    /**
     * The current workspace's slug, as a path segment.
     *
     * Throwing is the point. Media is only ever read or written inside a workspace request,
     * so no tenant means something is wrong — and the alternative to failing here is writing
     * one workspace's files to the disk root, where nothing sweeps them and no error is
     * raised anywhere.
     */
    private function tenantPrefix(): string
    {
        $slug = tenant('id');

        if (! is_string($slug) || $slug === '') {
            throw new RuntimeException('Media path requested outside a workspace.');
        }

        return $slug.'/';
    }
}
