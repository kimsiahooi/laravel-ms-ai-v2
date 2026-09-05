<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Reading a filter out of the query string without trusting its shape.
 *
 * **`$request->string()` fatals on `?search[]=x`.** An array reaches `Str::of()`, which
 * raises a TypeError — a 500 from a hand-edited URL, on every list in the app. The
 * hazard was found once while building the sort guard and fixed only there; `search`,
 * and later the unit and material filters, kept the fatal. One definition means the
 * next filter cannot reintroduce it by writing the obvious thing.
 *
 * `query()` rather than `input()` on purpose: a filter belongs to the URL, and reading
 * only the query string means a request body cannot supply one.
 *
 * Anything that is not a string — an array, a nested array, absent — reads as `''`,
 * which every caller already treats as "no filter". A URL somebody typed by hand is
 * then boring rather than broken.
 */
trait ReadsQueryValues
{
    protected function queryValue(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }
}
