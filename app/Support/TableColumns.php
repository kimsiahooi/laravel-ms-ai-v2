<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TableKey;
use App\Models\CentralUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Reads a stored column layout back into something a render can trust.
 *
 * The same job, and the same shape, as {@see TimeZones::resolve()}: whatever is in the
 * column, hand back a value the page can be built from, or the empty default. Nothing
 * stored reaches a prop unchecked.
 *
 * **Why validate what we wrote ourselves.** The endpoint that writes this validates every
 * field, so in the ordinary case there is nothing to catch. This is for the other cases —
 * a row edited by hand, a restored backup, a column renamed in a deploy — where the
 * alternative to dropping a bad entry is a shape the browser does not expect reaching the
 * SSR render, which fails the whole page rather than one list.
 *
 * Column ids are deliberately *not* checked against anything: the server has no list of
 * them, and the client already reconciles ids against what each page declares — see
 * `toColumnOrder()` in column-layout.ts. An id that no longer exists is dropped there,
 * which is the right place for it. This only guarantees the shape.
 */
final class TableColumns
{
    /** How many ids one list may store, matching the endpoint's own bound. */
    private const MAX_IDS = 40;

    /** Longest a column id may be, matching the endpoint's own bound. */
    private const MAX_ID_LENGTH = 64;

    /**
     * Every layout this user has saved, keyed by list.
     *
     * Empty for a guest, for a model with no such column, and for the great majority of
     * people — only a list somebody has actually changed is ever stored.
     *
     * @return array<string, array{order: array<int, string>, hidden: array<int, string>}>
     */
    public static function forUser(?Authenticatable $user): array
    {
        if (! $user instanceof User && ! $user instanceof CentralUser) {
            return [];
        }

        $stored = $user->table_columns;

        if (! is_array($stored)) {
            return [];
        }

        $layouts = [];

        foreach ($stored as $key => $layout) {
            // `is_string` is load-bearing, not defensive noise: json_decode hands back
            // whatever keys the stored object had, and tryFrom on an int is a TypeError
            // rather than a null.
            if (! is_string($key) || TableKey::tryFrom($key) === null) {
                continue;
            }

            if (! is_array($layout)) {
                continue;
            }

            $layouts[$key] = [
                'order' => self::ids($layout['order'] ?? null),
                'hidden' => self::ids($layout['hidden'] ?? null),
            ];
        }

        return $layouts;
    }

    /**
     * A list of column ids, or an empty one. Bounded on both count and length, because
     * the only thing between this and the SSR payload is what somebody stored.
     *
     * @return array<int, string>
     */
    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            if (is_string($id) && $id !== '' && mb_strlen($id) <= self::MAX_ID_LENGTH) {
                $ids[] = $id;
            }

            if (count($ids) === self::MAX_IDS) {
                break;
            }
        }

        // `array_values` after the unique: the keys have to stay a list, or json_encode
        // renders an object and the browser gets `{"0":"name"}` where it expects an array.
        return array_values(array_unique($ids));
    }
}
