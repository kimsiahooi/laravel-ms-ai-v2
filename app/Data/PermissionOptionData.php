<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PermissionAction;
use App\Support\TenantPermissions;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One checkbox in the role editor: the permission it grants, and the verb it is labelled with.
 *
 * **A value, not a sentence.** `action` is an enum case the browser looks up in `lang/`, and
 * the verb stands alone — "Edit" — because the group it sits in already names the noun. The
 * catalog used to ship a composed English label per checkbox, built as
 * `ACTION_VERB[$action].' '.lcfirst($screen)`, which `docs/LOCALIZATION.md` forbids twice over:
 * it concatenates rather than interpolates, and `lcfirst` means nothing in `zh_Hans` and puts
 * the words in the wrong order for Malay.
 *
 * `name` travels because it is what actually gets granted — the browser posts these strings
 * back and `RoleRequest` checks each one against {@see TenantPermissions::names()}.
 * It is never rendered.
 */
#[TypeScript]
final class PermissionOptionData extends Data
{
    public function __construct(
        /** The seeded permission name, `categories.view`. Posted back, never shown. */
        public string $name,
        public PermissionAction $action,
    ) {}
}
