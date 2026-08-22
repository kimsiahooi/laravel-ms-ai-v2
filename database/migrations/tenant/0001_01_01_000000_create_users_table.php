<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant users. Lives in EACH tenant's database, so there is no tenant_id column —
 * the database itself is the scope. The central super-admins table is a separate
 * `users` table in the central database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Per-user UI language; falls back to the tenant's, then the app's.
            $table->string('locale', 12)->nullable();
            $table->rememberToken();
            $table->timestamps();
            // Disables a user while keeping the row for restore. The unique index on
            // `email` counts trashed rows, so the address stays reserved.
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
