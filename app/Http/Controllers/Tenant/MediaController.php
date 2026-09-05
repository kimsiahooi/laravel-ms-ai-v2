<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\StreamsMedia;
use App\Support\Media\MediaOwners;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves one uploaded file: `GET /{tenant}/media/{media}` for the original, and
 * `/{tenant}/media/{media}/{conversion}` for a generated size.
 *
 * **The URL has no extension**, and that is not an oversight. Some nginx setups serve
 * anything ending in `.png` or `.jpg` straight from the docroot with `try_files $uri
 * =404` and never reach PHP at all — which for a private disk means every image 404s in
 * production and works perfectly in development. An extension-less path cannot be
 * mistaken for a static file.
 *
 * Route-model binding resolves `{media}` on the default connection, which
 * InitializeTenancyByPath has already pointed at this workspace's own database. So a
 * workspace cannot reach another's files by guessing an id: the row is not there to find.
 *
 * The id is also the version. A re-upload replaces the media row (the collections are
 * `singleFile()`), so the new file has a new id and therefore a new URL — a URL stored
 * anywhere always names the file it named when it was written, and a deleted id 404s
 * rather than showing whatever took its place.
 */
final class MediaController
{
    use StreamsMedia;

    public function __invoke(Request $request, Media $media, string $conversion = ''): StreamedResponse
    {
        // A media row knows what it belongs to, so the permission is already on the row —
        // v1 left this route open to any signed-in user, which meant somebody with no
        // products permission at all could read every product photo in the workspace by
        // counting up from `/media/1`. Unregistered types are refused, not allowed; the
        // same registry decides where their files are written, so a collection cannot be
        // storable and unservable at the same time.
        $permission = MediaOwners::permission($media->model_type);

        // 404 rather than 403 for an unmapped type: "you may not see this" is itself
        // information about a file whose existence is none of the asker's business.
        abort_if($permission === null, 404);
        abort_unless($request->user()?->can($permission) === true, 403);

        // A conversion that was never generated is not a smaller version of this file,
        // it is nothing. The DTO that builds these URLs falls back to the original
        // rather than pointing here, so reaching this is a URL somebody typed.
        abort_if($conversion !== '' && ! $media->hasGeneratedConversion($conversion), 404);

        return $this->streamMedia($request, $media, $conversion);
    }
}
