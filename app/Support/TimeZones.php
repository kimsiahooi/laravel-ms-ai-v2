<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\Tenant\PurchaseOrderRequest;
use App\Models\BusinessSetting;
use DateTimeZone;
use Illuminate\Http\Request;
use Throwable;

/**
 * Which clock a request is rendered against.
 *
 * The database is UTC and stays UTC — every column, every Carbon, every ISO-8601
 * string on the wire. This class only decides how those instants are *displayed*.
 *
 * **A workspace has one clock, and everyone reads on it.** The zone set in business
 * settings is the reference: a date is shown the same way to the buyer at the next desk
 * and to a colleague reading from another country, because it is the company's calendar
 * being quoted rather than the reader's. That is the rule {@see resolve} implements.
 *
 * The browser's own zone survives as a *fallback*, for `/admin` — where there is no
 * business to have a clock — and for the moment before a workspace is resolved. It
 * reaches the server through a cookie (the inline script in `app.blade.php`, beside the
 * one that applies dark mode before first paint) rather than being read during a render:
 * under SSR the same markup is produced twice, once in PHP's Node process and once in
 * the browser, and a zone the browser decided for itself would make those two disagree.
 * That is a React #418 hydration mismatch, the trap {@see Locales} avoids by refusing to
 * read `navigator.language`. Nothing anywhere calls `resolvedOptions()` during a render.
 *
 * Resolving from a stored setting rather than a cookie makes that safer still: a server
 * value cannot differ between the two renders at all.
 *
 * An IANA identifier rather than a numeric offset, because an offset is only correct
 * until the next daylight-saving change. `Asia/Kuala_Lumpur` has none; `America/New_York`
 * is -05:00 in December and -04:00 in June, and only the name knows that.
 */
final class TimeZones
{
    /**
     * What a request renders in when the browser has not reported anything yet — the
     * first page of a fresh browser, a client with cookies blocked, a console command.
     * UTC because that is what the stored value already is: the fallback shows the
     * truth, just not the viewer's version of it.
     */
    public const FALLBACK = 'UTC';

    /** The cookie the browser writes its detected zone to. Set by JS, so unencrypted. */
    public const COOKIE = 'timezone';

    /**
     * Longest identifier in the tzdb is `America/Argentina/ComodRivadavia` at 32, so 64
     * is generous. The point is to stop a hand-edited cookie turning into a large
     * `in_array` argument, not to be precise.
     */
    private const MAX_LENGTH = 64;

    /** @var array<string, true>|null the tzdb, flipped for O(1) lookup */
    private static ?array $identifiers = null;

    /** @var array<string, string> workspace zone by tenant key — see workspace() */
    private static array $workspaces = [];

    public static function supports(?string $timezone): bool
    {
        if (! is_string($timezone) || $timezone === '' || strlen($timezone) > self::MAX_LENGTH) {
            return false;
        }

        // ALL_WITH_BC, not the default group. Without the aliases a browser reporting
        // `Asia/Calcutta` or `US/Pacific` — both still emitted by real systems — is
        // rejected and that person renders in UTC forever: the cookie is written, the
        // page reloads once, PHP refuses it, and the script then short-circuits on
        // every later visit because the cookie already holds what the browser reports.
        // No loop, but no correct zone either, and nothing says why.
        self::$identifiers ??= array_fill_keys(
            DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
            true,
        );

        return isset(self::$identifiers[$timezone]);
    }

    /**
     * The zone to render this request in: the workspace's, then the browser's, then UTC.
     *
     * **The workspace wins whenever there is one.** Its clock is what the business reads
     * on, and a date that changed meaning depending on who opened the page would not be a
     * date the business could talk about. The browser's cookie answers only where there
     * is no workspace — `/admin` — and before one has been resolved.
     *
     * The cookie is validated rather than trusted wherever it is used: anyone can write
     * it, and it is about to be handed to `Intl.DateTimeFormat`, which throws a
     * RangeError on an identifier it does not know and takes the whole SSR render down.
     */
    public static function resolve(Request $request): string
    {
        $workspace = self::workspaceOrNull();

        if ($workspace !== null) {
            return $workspace;
        }

        $reported = $request->cookie(self::COOKIE);

        // `cookie()` widens to array|string|null; only the string case can be a zone.
        if (is_string($reported) && self::supports($reported)) {
            return $reported;
        }

        return self::FALLBACK;
    }

    /**
     * Every zone a person may choose from, for the settings picker.
     *
     * The canonical list only — `ALL`, not the `ALL_WITH_BC` that {@see supports} accepts.
     * The two differ on purpose: a browser may still report `Asia/Calcutta`, and refusing
     * that would strand whoever is using it on UTC, but nobody should be offered a
     * deprecated alias in a menu when `Asia/Kolkata` is right there.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /**
     * The workspace's own clock, for callers that need it whether or not a request exists.
     *
     * A console command, a queued job and an inbound form all reach for this: it is the
     * calendar a picked delivery day is anchored against ({@see PurchaseOrderRequest})
     * and the one a financial year turns over on ({@see DocumentNumberGenerator}).
     * Falls back to UTC outside a workspace, where there is no business to have a clock.
     */
    public static function workspace(): string
    {
        return self::workspaceOrNull() ?? self::FALLBACK;
    }

    /**
     * The workspace's clock, or null when there is no workspace at all.
     *
     * The distinction {@see workspace} cannot make and {@see resolve} needs: a workspace
     * that has deliberately chosen UTC must win over a browser cookie, while *no*
     * workspace must fall through to it.
     *
     * **Memoised per tenant, not globally.** A queue worker is a long-lived process that
     * handles jobs for many workspaces in turn, and a single static string would leak one
     * workspace's zone into another's job. The tenant key is part of the cache key for
     * that reason.
     */
    private static function workspaceOrNull(): ?string
    {
        $tenant = tenant();

        if ($tenant === null) {
            return null;
        }

        $key = (string) $tenant->getTenantKey();

        if (! array_key_exists($key, self::$workspaces)) {
            self::$workspaces[$key] = self::readWorkspaceZone();
        }

        return self::$workspaces[$key];
    }

    /**
     * Forget the memoised workspace zones.
     *
     * Called when the settings are saved, so the rest of the request renders against what
     * was just chosen rather than what was read at the top of it.
     */
    public static function forget(): void
    {
        self::$workspaces = [];
    }

    /**
     * The stored zone, guarded twice.
     *
     * `supports()` because the column could hold anything a past migration or a hand-run
     * UPDATE left there, and this value reaches `Intl.DateTimeFormat` in the SSR process
     * exactly as the cookie does. The try/catch because this runs inside `share()` on
     * every request, including ones where the tenant database is unreachable or has not
     * been migrated yet — a settings lookup must not be what takes a page down.
     */
    private static function readWorkspaceZone(): string
    {
        try {
            $stored = BusinessSetting::current()->timezone;
        } catch (Throwable) {
            return self::FALLBACK;
        }

        return self::supports($stored) ? $stored : self::FALLBACK;
    }
}
