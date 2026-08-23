<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw materials — what the workspace buys in and consumes to make something else.
 *
 * Per-tenant, so there is no tenant_id: the database is the scope.
 *
 * Unlike a supplier or a customer, this is a thing rather than a party, and the
 * difference shows in what is required. A material is useless to the rest of the system
 * without a code to refer to it by and a unit to count it in, so `sku` and `unit` are
 * both NOT NULL. Every quantity a purchase order, a stock movement or a bill of
 * materials will ever record is a number *of* that unit; a material without one would
 * produce rows whose quantity means nothing.
 *
 * `unit` stays a varchar rather than becoming a database enum, but the values it may
 * hold are {@see Unit}'s and nothing else — validation refuses the rest. A
 * varchar keeps adding a unit a code change rather than an ALTER on every tenant
 * database, and leaves room for a per-workspace units table later without a data
 * migration: the column already holds the code such a table would key on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_materials', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Unique, and trashed rows still count — the index does not know about
            // soft deletes. See RawMaterialRequest for what that means for the person
            // typing a code back in.
            $table->string('sku', 100)->unique();
            // Not unique: two materials can carry the same manufacturer barcode, and
            // refusing the second would be refusing the truth. Indexed because the
            // scanner resolves a scanned value by exact match, not by search.
            $table->string('barcode', 100)->nullable()->index();
            // Holds an App\Enums\Unit code — 'kg', 'pcs'. v1 let people type this
            // freely, which made "kg" and "KG" two different units to a stock engine
            // that would later be adding their quantities together.
            $table->string('unit', 20);
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // Purchase orders, stock movements and BOM lines will all reference a
            // material; deleting one must not orphan the history that names it.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_materials');
    }
};
