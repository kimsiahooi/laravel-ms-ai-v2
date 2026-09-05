<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\RawMaterial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's bill of materials: which raw materials go into it, and how much of each
 * is needed to make **one** unit.
 *
 * Per-unit rather than per-batch, and that is the whole design. A production order for
 * 250 units multiplies this by 250; storing a batch quantity would mean every order for
 * a different size needed its own bill. It also means the number here is small and
 * often fractional — 0.35 kg of resin per unit — which is what the scale below is for.
 *
 * The row carries no soft delete and no `created_by`. A bill is not a record of
 * anything that happened; it is the current answer to "what goes into this", replaced
 * wholesale each time it is edited. What *did* happen is the production order, which
 * snapshots its own copy of these lines at creation, so editing a bill never rewrites
 * an order that was already placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_items', function (Blueprint $table): void {
            $table->id();
            // cascadeOnDelete, unlike the nullable keys on `products`. A line with no
            // product is not a bill of anything, and both parents are hard-deleted only
            // — a soft delete leaves the row untouched, so a restored product comes back
            // with its bill intact.
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(RawMaterial::class)->constrained()->cascadeOnDelete();
            // decimal(15,4) — the first real number in this schema, and the shape every
            // quantity and price column that follows will use.
            //
            // Fixed-point, not a float: this is multiplied by an order size and summed
            // across a bill, and binary floating point cannot hold 0.1 exactly. Four
            // decimal places because a per-unit figure is where the small quantities
            // live; two would round 0.0125 kg of pigment to a cent's worth of nothing.
            //
            // MySQL in strict mode errors on too many INTEGER digits but silently ROUNDS
            // extra decimal places, so 1.12345 would be accepted and stored as 1.1235.
            // TenantFormRequest::decimalRules() is what refuses it instead.
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            // One line per material. Two lines for the same material are not a bill with
            // a duplicate, they are two answers to one question — and the editor would
            // have no way to show which of them it just changed.
            $table->unique(['product_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_items');
    }
};
