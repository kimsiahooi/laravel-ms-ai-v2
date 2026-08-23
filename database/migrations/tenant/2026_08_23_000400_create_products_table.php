<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products — what the workspace sells.
 *
 * The anchor of the catalog. Sales order lines, production orders and bills of
 * materials all point here, which is why it is the last of the five to land: everything
 * it can reference now exists.
 *
 * `sku` and `unit` are required for the same reasons they are on raw materials — a
 * product is referred to by its code and counted in its unit everywhere downstream.
 *
 * `category_id` and `supplier_id` are nullable. A product is often created before
 * anyone has decided where it files or who supplies it, and refusing to save it until
 * they have just produces a catalog of placeholder categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Unique, and trashed rows still count — see ProductRequest.
            $table->string('sku', 100)->unique();
            // Not unique, and indexed: the scanner resolves a scanned value by exact
            // match. Two products can carry the same manufacturer barcode.
            $table->string('barcode', 100)->nullable()->index();
            $table->text('description')->nullable();
            // nullOnDelete fires on a real force-delete of the parent, not on the soft
            // delete that the category and supplier screens actually perform — so a
            // deleted category leaves its products pointing at a trashed row rather
            // than losing the association. The listing resolves the name either way.
            $table->foreignIdFor(Category::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Supplier::class)->nullable()->constrained()->nullOnDelete();
            // Holds an App\Enums\Unit code, same as raw_materials.
            $table->string('unit', 20);
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // Sales orders will name the product they sold; deleting one must not
            // orphan the history that names it.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
