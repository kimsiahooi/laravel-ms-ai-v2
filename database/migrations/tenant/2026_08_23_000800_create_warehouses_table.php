<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\LocationController;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warehouses — the buildings stock actually sits in. Each one belongs to a site.
 *
 * `restrictOnDelete` on the site, not cascade: a site's warehouses hold stock, and
 * removing a site must never quietly take that with it. The database refusing is the
 * backstop; the controller refuses first, with a sentence naming the warehouses in
 * the way — see {@see LocationController::destroy()}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Location::class)->constrained()->restrictOnDelete();
            $table->string('name');
            // Nullable AND unique, workspace-wide rather than per site: a code exists
            // to be written on a transfer note, and one that only means something once
            // you also know the site is not a code.
            $table->string('code', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // A movement names the warehouse it moved through, and a movement that
            // cannot say where it happened is not a record.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
