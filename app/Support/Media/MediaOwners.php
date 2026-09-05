<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Product;
use RuntimeException;

/**
 * Every kind of record that can own a file, and the two facts the app needs about it:
 * the folder its files live in, and the permission that may read them.
 *
 * One registry rather than two maps in two files, because both answers are needed for the
 * same reason — a media row knows what it belongs to, and everything else follows from
 * that. Registering a new collection is one line here instead of one line in the path
 * generator and another, easily forgotten, in the controller that serves it.
 *
 * **Unknown types are refused, not defaulted.** A model with no entry cannot be stored
 * (the path generator throws) and cannot be served (the controller 404s). Both failures
 * are loud and immediate — the first upload in development — which is the failure worth
 * having when the alternative is files landing in a folder nobody swept and being served
 * to whoever asks.
 *
 * The folder is spelled out rather than derived from the class name. Deriving it would
 * save this list and cost a silent orphaning of every file the day someone renames the
 * model; a literal survives the rename, and if the folder should change too, that becomes
 * a deliberate edit with a migration behind it.
 */
final class MediaOwners
{
    /**
     * @var array<class-string, array{folder: string, permission: string}>
     */
    private const OWNERS = [
        Product::class => ['folder' => 'products', 'permission' => 'products.view'],
    ];

    /**
     * The folder this model's files live under, inside its workspace.
     *
     * @throws RuntimeException when the model is not registered
     */
    public static function folder(string $modelType): string
    {
        return self::of($modelType)['folder'];
    }

    /** The permission required to read this model's files, or null if unregistered. */
    public static function permission(string $modelType): ?string
    {
        return self::OWNERS[$modelType]['permission'] ?? null;
    }

    /**
     * @return array{folder: string, permission: string}
     *
     * @throws RuntimeException
     */
    private static function of(string $modelType): array
    {
        return self::OWNERS[$modelType] ?? throw new RuntimeException(
            "No media folder is registered for [{$modelType}]. Add it to ".self::class.'.',
        );
    }
}
