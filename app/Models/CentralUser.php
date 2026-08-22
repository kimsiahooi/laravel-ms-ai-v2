<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Super-admin. Pinned to the central connection (via CentralConnection) and
 * authenticated only through the `central` guard at /admin. Distinct from the
 * per-tenant {@see User}, which lives in each tenant's own database.
 *
 * Both models use the `users` table — but in different databases, which is why the
 * connection pin matters: without it this would read the tenant's users mid-request.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class CentralUser extends Authenticatable
{
    use CentralConnection;
    use Notifiable;

    // SoftDeletes disables an admin (excluded from auth) while keeping the row for
    // restore. The `email` unique index counts trashed rows, so a soft-deleted
    // admin's email stays reserved until restored or force-deleted.
    use SoftDeletes;

    protected $table = 'users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
