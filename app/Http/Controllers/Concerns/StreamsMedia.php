<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one way a file leaves the private `assets` disk.
 *
 * It streams rather than reads: a response built from `file_get_contents` holds the
 * whole image in PHP's memory for the length of the request, and a list of twenty-five
 * products asks for twenty-five of them at once.
 *
 * The cache headers are the other half of the job. Media URLs carry the media id, and
 * the id changes on every re-upload — the collections are `singleFile()`, so a new file
 * means a new row — which already makes a stored URL impossible to serve stale. The
 * validators cover the narrower case the id cannot: the *same* row re-processed, e.g. a
 * conversion regenerated after its definition changed. `no-cache` means "ask first, do
 * not assume", so the browser revalidates and gets a 304 when nothing moved.
 *
 * `setPrivate()` matters on a shared host: without it a proxy is entitled to keep one
 * workspace's photo and hand it to the next request that asks for the same URL.
 */
trait StreamsMedia
{
    /**
     * @param  string  $conversion  A registered conversion name, or '' for the original.
     */
    protected function streamMedia(Request $request, Media $media, string $conversion = ''): StreamedResponse
    {
        // A conversion may live on a different disk from its original — medialibrary
        // records both on the row, and asking the wrong one is a 404 on a file that
        // exists.
        $disk = Storage::disk($conversion === '' ? $media->disk : $media->conversions_disk);
        $path = $media->getPathRelativeToRoot($conversion);

        abort_unless($disk->exists($path), 404);

        $response = $disk->response($path);

        // The conversion belongs in the tag: the original and its thumbnail are the same
        // row with the same timestamp, and only the name tells them apart.
        $etag = $media->getKey().($conversion === '' ? '' : "-{$conversion}");

        if ($media->updated_at !== null) {
            $etag .= '-'.$media->updated_at->getTimestamp();
        }

        $response->setPrivate();
        $response->setEtag($etag);
        $response->setLastModified($media->updated_at);
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
