<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person added by an administrator starts with a password that administrator typed, and
 * this is the flag that stops it staying that way.
 *
 * **Why there is a password to change at all.** Adding a colleague could have sent them a
 * link to set their own, and Fortify's reset-password machinery is already enabled and would
 * have carried one. It does not, deliberately: a workspace has to be able to onboard somebody
 * on a box where SMTP is not configured, and `MAIL_MAILER=log` is the default. The cost of
 * that choice is exactly this column — a temporary password that never has to change is a
 * permanent one that two people know, and the second person never agreed to it.
 *
 * **Default false, so nobody already in a workspace is affected.** The administrator created
 * by `admin:create` chose their own password and is not being asked to choose again; only a
 * row written by the Users screen sets this true.
 *
 * Cleared by `SecurityController::update`, which is the one place a person changes their own
 * password — so the flag cannot be cleared by anything except the act it is waiting for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
