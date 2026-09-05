<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;

/**
 * Replaces a product's bill of materials with the lines given.
 *
 * **Delete-then-insert, not a diff.** A bill is a small list a person just finished
 * arranging, and reconciling it line by line would mean matching rows by material id,
 * detecting reorders, and deciding what a moved line means — a lot of code to preserve
 * `bom_items.id` values that nothing refers to. Nothing points at a line: production
 * orders snapshot their own copy of the materials at creation precisely so that editing
 * a bill cannot rewrite an order already placed.
 *
 * **The transaction is what makes that safe.** Between the delete and the last insert
 * the product has no bill, and a failure there — a constraint, a lost connection —
 * would leave it that way permanently. Wrapping both makes the replacement all or
 * nothing, so the worst case is the bill that was already there.
 *
 * Lives here rather than in the controller because it is two statements that must
 * happen together, which is the definition of something a controller should delegate.
 */
final class ReplaceBom
{
    /**
     * @param  list<array{raw_material_id: int|string, quantity: int|float|string}>  $lines
     *                                                                                       Validated by BomRequest. An empty list clears the bill, which is how the
     *                                                                                       editor removes the last material.
     */
    public function handle(Product $product, array $lines): void
    {
        $product->getConnection()->transaction(function () use ($product, $lines): void {
            // `delete()` on the relation, not `truncate()` on the table: the constraint
            // is per product, and this runs inside a tenant's own database where
            // another product's bill is one row away.
            $product->bomItems()->delete();

            foreach ($lines as $line) {
                $product->bomItems()->create([
                    'raw_material_id' => $line['raw_material_id'],
                    'quantity' => $line['quantity'],
                ]);
            }
        });

        // The relation was loaded before this ran — for the listing that rendered the
        // editor — so without this the redirect re-serialises the bill it replaced.
        $product->unsetRelation('bomItems');
    }
}
