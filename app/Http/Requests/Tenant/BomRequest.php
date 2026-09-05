<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

/**
 * A product's whole bill of materials, in one request.
 *
 * The endpoint replaces the bill rather than patching it, so what arrives is the
 * complete list — every line that should exist afterwards, in order. Editing a
 * quantity, removing a material and adding two others are one save.
 *
 * **`nullable`, not `present`.** A bill with no lines is legitimate; it is how one is
 * cleared. But an HTML form with no rows renders no `items[…]` inputs at all, so the
 * key is simply absent — there is no markup that submits an empty array. Since this
 * endpoint's entire contract is "here is the new bill", absent and empty mean the same
 * thing, and demanding a key the browser cannot send would only be a rule the form has
 * to work around.
 *
 * `max:200` is a ceiling on the request, not a business rule. A bill of two hundred
 * materials is not a bill anyone typed.
 *
 * Mirrored in the browser by resources/js/lib/validation/schemas/bom.ts;
 * `bun run check:validation` fails if the two stop covering the same fields.
 */
final class BomRequest extends TenantFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.raw_material_id' => [
                'required',
                // Two lines for the same material are two answers to one question, and
                // the unique index would refuse the second with a 500. This refuses it
                // with a sentence, pointing at the line that repeats.
                'distinct',
                ...$this->foreignKey('raw_materials'),
            ],
            'items.*.quantity' => ['required', ...$this->decimalRules()],
        ];
    }

    /**
     * The validated lines, in the types the columns want.
     *
     * The conversion belongs here rather than in the action: a form submits strings,
     * `bom_items.raw_material_id` is an integer and `quantity` is decimal(15,4), and
     * the class that declared the rules is the one that knows which is which.
     *
     * `quantity` stays a **string**. Casting it to float would undo the reason the
     * column is fixed-point — 0.1 has no exact binary representation, and this number
     * gets multiplied by every production order. The string that passed `decimal:0,4`
     * is already the exact value; handing it to Eloquent unchanged is what keeps it so.
     *
     * @return list<array{raw_material_id: int, quantity: string}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->array('items') as $line) {
            // `array()` is untyped by nature. The rules above have already refused
            // anything that is not a pair of scalars under an integer key, so this is
            // the analyser being told the shape rather than a case that can occur.
            if (! is_array($line)) {
                continue;
            }

            $lines[] = [
                'raw_material_id' => (int) $line['raw_material_id'],
                'quantity' => (string) $line['quantity'],
            ];
        }

        return $lines;
    }
}
