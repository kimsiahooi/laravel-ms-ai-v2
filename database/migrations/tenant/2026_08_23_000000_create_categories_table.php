<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product categories — the first catalog table.
 *
 * Lives in EACH tenant's database, so there is no tenant_id column: the database is
 * the scope. That is also what makes the unique index on `name` mean "unique within
 * this workspace" without any extra qualification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            // text, not string: the form accepts 1000 characters and varchar(255)
            // would reject anything past 255 with a database error rather than a
            // validation message.
            $table->text('description')->nullable();
            // Nullable, and nulled rather than cascaded when the user is force-deleted:
            // losing a person must never take the catalog with it.
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // Deleting a category must not orphan the products filed under it, so the
            // row is kept and the link stays resolvable.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
