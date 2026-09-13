<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\TenantPermissions;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a permission lets somebody do to a screen.
 *
 * The value is the second half of every permission name — `categories.view` — so these
 * strings are seeded data and cannot be renamed without a migration across every tenant
 * database.
 *
 * **The words a person reads are not here.** They live in `lang/{locale}/permissions.php`
 * keyed by these values, and the role editor composes `permissions.action.{value}` the way
 * the ledger composes `stock-movements.reason.{value}`. v1 carried an English `label()` on
 * its enums, and so did this catalog until the role editor needed it in three languages.
 *
 * `#[TypeScript]` emits `App.Enums.PermissionAction`, which is what makes the browser's
 * template-literal key type-check — so an action added here without its translation is a
 * `tsc` error rather than an unlabelled checkbox.
 */
#[TypeScript]
enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    /**
     * The resource route suffix this action guards.
     *
     * `create` guards `store` rather than `create`, because the permission is to *make* the
     * thing; the GET form page that precedes it is mapped separately — see
     * {@see TenantPermissions::routeMap()}, which is where forgetting it has twice left a
     * page open to anybody with a login.
     */
    public function routeSuffix(): string
    {
        return match ($this) {
            self::View => 'index',
            self::Create => 'store',
            self::Update => 'update',
            self::Delete => 'destroy',
        };
    }
}
