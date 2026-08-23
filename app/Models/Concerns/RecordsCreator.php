<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps whoever is signed in onto `created_by`, and exposes the `creator` relation
 * that every "Created by" column reads.
 *
 * Nullable throughout, deliberately: a row made by a seeder, a job or an artisan
 * command has no user behind it, and inventing one would be worse than admitting
 * there was none.
 *
 * The consuming model needs a nullable `created_by` column. The value is set through
 * `setAttribute`, not mass-assigned, so `created_by` must NOT appear in the model's
 * `#[Fillable]` — a client could otherwise claim a record was created by someone else.
 */
trait RecordsCreator
{
    public static function bootRecordsCreator(): void
    {
        static::creating(function (Model $model): void {
            $id = Auth::id();

            // Only overwrite an unset value: a seeder or an import that already knows
            // the author has said so on purpose.
            if (is_int($id) && $model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', $id);
            }
        });
    }

    /**
     * The tenant user who created this record, or null for system rows.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
