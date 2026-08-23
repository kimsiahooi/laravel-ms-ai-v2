<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Category;
use Illuminate\Validation\Rule;

/**
 * Create and update share one set of rules — the only difference is which row the
 * uniqueness check is allowed to ignore.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/category.ts;
 * `bun run check:validation` fails if the two stop covering the same fields. Field
 * names come from `lang/{locale}/validation.php` under `attributes`, which both
 * layers read, so neither can drift into calling the same field something else.
 */
final class CategoryRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Unique within this workspace — the database IS the tenant, so no
                // further qualification is needed. Ignores itself on update.
                //
                // Trashed rows count, deliberately: MySQL's unique index counts them
                // too, and a rule that excluded them would pass validation and then
                // fail the INSERT as a 500. Same tradeoff the tenant `users` table
                // already documents for email addresses.
                Rule::unique('categories', 'name')
                    ->ignore($category instanceof Category ? $category->getKey() : null),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
