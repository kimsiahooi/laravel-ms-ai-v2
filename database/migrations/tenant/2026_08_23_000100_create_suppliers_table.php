<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suppliers — who the workspace buys from.
 *
 * Per-tenant, so there is no tenant_id: the database is the scope.
 *
 * `name` is deliberately NOT unique. Two suppliers can genuinely trade under the same
 * name — different branches, or a rename that has not propagated — and refusing the
 * second is worse than allowing it. The email is unique instead, because that is the
 * field a duplicate actually breaks: it is how a purchase order reaches someone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            // Nullable AND unique: MySQL permits any number of NULLs in a unique index,
            // so a supplier with no email on file never collides with another.
            $table->string('email')->nullable()->unique();
            $table->string('tax_id', 100)->nullable();
            $table->string('phone', 50)->nullable();
            // text, not string: both accept 1000 characters from the form, and
            // varchar(255) would reject the rest with a database error.
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // Purchase orders will reference suppliers; deleting one must not orphan
            // the history that names it.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
