<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PermissionScreen;
use App\Support\TenantPermissions;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One screen's worth of the role editor — the fieldset and the checkboxes inside it.
 *
 * The browser renders `screen` through `permissions.screen.{value}` as the group's `<legend>`
 * and each option through `permissions.action.{value}`, so a screen reader announces
 * "Categories — Edit" out of real markup rather than out of a string somebody glued together
 * on the server. That is both the accessible shape and the localizable one, which is not a
 * coincidence: a label built by concatenation can only ever be assembled in one language.
 *
 * Produced by {@see TenantPermissions::matrix()}, which is the only caller and
 * always emits every screen in catalog order.
 */
#[TypeScript]
final class PermissionGroupData extends Data
{
    public function __construct(
        public PermissionScreen $screen,
        /** @var list<PermissionOptionData> */
        public array $permissions,
    ) {}
}
