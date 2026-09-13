<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\TenantRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A user inside ONE tenant's database.
 *
 * There is no tenant_id column: the row only exists in that tenant's database, and
 * the default connection already points there once tenancy is initialized. The
 * central super-admin is a separate model, {@see CentralUser}.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $locale
 * @property array<array-key, mixed>|null $table_columns
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    // SoftDeletes disables a user (excluded from auth/login) while keeping the row
    // for admin-driven restore. Note: the `email` unique index counts trashed rows,
    // so a soft-deleted user's email stays reserved until it is restored or
    // force-deleted.
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The people who can still fix anything.
     *
     * **The lockout invariant is written here, once**: a workspace must always have at least
     * one active user holding {@see TenantRoles::ADMIN}. Not "at least one user who can edit
     * users" — a custom role could hold `users.update` and nothing else, and a workspace whose
     * only remaining administrator has been demoted to that is a workspace that can add
     * people and never change a tax rate again. Administrator is the only role the seeder
     * guarantees holds the whole catalog and the only one the UI refuses to edit, so it is
     * the only one worth counting.
     *
     * Soft-deleted rows are excluded by the model's own global scope, so "active" is free.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function administrators(Builder $query): void
    {
        $query->role(TenantRoles::ADMIN);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            // Set only by the Users screen, cleared only by SecurityController::update.
            // Never mass-assigned: it is not in #[Fillable], so a crafted payload on the
            // profile form cannot clear somebody's obligation to choose their own password.
            'must_change_password' => 'boolean',
            // Which columns this person looks at, per list. Never mass-assigned — it is
            // written with forceFill() from one endpoint, the way `locale` is.
            'table_columns' => 'array',
        ];
    }
}
