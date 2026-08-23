<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * A soft-delete-aware `exists` rule: the row must exist AND not be trashed.
 *
 * `Rule::exists()` queries the table directly and so bypasses the SoftDeletes global
 * scope — a deleted category would still satisfy it, and the product would come back
 * filed under something the workspace believes it removed. Every foreign-key rule would
 * otherwise have to remember `->whereNull('deleted_at')`; this is that, once.
 *
 *     'supplier_id' => ['nullable', ActiveExists::of('suppliers')]
 */
final class ActiveExists
{
    public static function of(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->whereNull('deleted_at');
    }
}
