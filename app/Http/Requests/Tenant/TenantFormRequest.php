<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every per-tenant form request.
 *
 * The routes are already behind `auth:web` and AuthorizeTenantRoute, so this
 * `authorize()` is belt-and-suspenders — it refuses a request that somehow arrived
 * with no bound user. Subclasses declare `rules()` and nothing else.
 *
 * Deliberately empty beyond that. v1's version carried decimal helpers for money and
 * quantity columns; those arrive with the stock and order modules that need them,
 * alongside the zod primitives that mirror them. Adding them now would mean guessing
 * at both halves.
 */
abstract class TenantFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
