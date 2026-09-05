<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites — the first stock table, and the top of the hierarchy everything stored is
 * eventually addressed by: a site owns warehouses, a warehouse holds stock.
 *
 * Per-tenant, so there is no tenant_id: the database is the scope, which is also what
 * makes the unique index on `code` mean "unique within this workspace".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Nullable AND unique. MySQL permits any number of NULLs in a unique
            // index, so "most sites have no code, and the ones that do must not
            // collide" is expressible as one index rather than as a rule somewhere.
            $table->string('code', 50)->nullable()->unique();
            // text, not string: the form accepts 1000 characters and varchar(255)
            // would reject anything past 255 with a database error rather than a
            // validation message.
            $table->text('address')->nullable();
            // Nullable, and nulled rather than cascaded when the user is force-deleted:
            // losing a person must never take the sites with it.
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // A movement that happened at a site names the site it happened at, and a
            // movement that cannot say where it happened is not a record.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
