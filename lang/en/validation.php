<?php

declare(strict_types=1);

/**
 * The validation messages this app can actually emit, translated.
 *
 * Deliberately a *subset* of the framework's file, not a copy of it. Laravel merges
 * `lang/{locale}/validation.php` over its own with `array_replace_recursive`, so every
 * rule left out here keeps the framework's wording — and `en` below is quoted from the
 * framework verbatim, so English output is byte-identical to having no file at all.
 * The file exists for `ms` and `zh_Hans`, and `en` is here only to give them a base
 * locale to reach parity with (`bun run check:i18n` compares the three key-for-key).
 *
 * Each module adds the rules it introduces as it lands; `check:i18n` fails when a rule
 * a FormRequest uses has no message here, so a new rule cannot silently ship English
 * into a Malay screen.
 *
 * These same keys are what the browser's zod schemas resolve through
 * `resources/js/lib/validation/primitives.ts`, which is what keeps the two layers
 * refusing a value with one sentence rather than two.
 */

return [

    'array' => 'The :attribute field must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'date' => 'The :attribute field must be a valid date.',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'different' => 'The :attribute and :other must be different.',
    'email' => 'The :attribute field must be a valid email address.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'gt' => [
        'numeric' => 'The :attribute field must be greater than :value.',
    ],
    'gte' => [
        'numeric' => 'The :attribute field must be greater than or equal to :value.',
    ],
    'image' => 'The :attribute field must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'min' => [
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'numeric' => 'The :attribute field must be a number.',
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',
    'unique' => 'The :attribute has already been taken.',

    /*
     * What `:attribute` is replaced with. Laravel reads this automatically, which is
     * why no FormRequest needs an `attributes()` method — and the zod primitives read
     * the same keys, so a field is named identically on both sides of the wire.
     */
    'attributes' => [
        'address' => 'address',
        'admin_email' => 'administrator email',
        'admin_name' => 'administrator name',
        'admin_password' => 'administrator password',
        'barcode' => 'barcode',
        'base_currency' => 'base currency',
        'category_id' => 'category',
        'city' => 'city',
        'code' => 'code',
        'contact_person' => 'contact person',
        'counted_quantity' => 'counted quantity',
        'country_code' => 'country',
        'currencies' => 'currencies',
        'currencies.*' => 'currency',
        'currency' => 'currency',
        'description' => 'description',
        'email' => 'email',
        'exchange_rate' => 'exchange rate',
        'expected_date' => 'expected date',
        'financial_year_start_month' => 'financial year start month',
        'image' => 'photo',
        'item' => 'item',
        'items' => 'materials',
        'items.*.discount_type' => 'discount type',
        'items.*.discount_value' => 'discount',
        'items.*.item' => 'material',
        'items.*.quantity' => 'quantity',
        'items.*.raw_material_id' => 'material',
        'items.*.taxable' => 'taxable',
        'items.*.unit_cost' => 'unit cost',
        'line' => 'line',
        'location_id' => 'site',
        'min_stock' => 'reorder level',
        'name' => 'name',
        'notes' => 'notes',
        'number_reset' => 'numbering restart',
        'phone' => 'phone',
        'postcode' => 'postcode',
        'purchase_order_prefix' => 'purchase order prefix',
        'purchase_return_prefix' => 'purchase return prefix',
        'quantity' => 'quantity',
        'registration_no' => 'registration number',
        'remove_image' => 'remove photo',
        'sales_order_prefix' => 'sales order prefix',
        'sales_return_prefix' => 'sales return prefix',
        'sku' => 'SKU',
        'slug' => 'slug',
        'sst_registration_no' => 'SST/GST registration number',
        'state_code' => 'state code',
        'supplier_id' => 'supplier',
        'tax_id' => 'tax ID',
        'tax_label' => 'tax label',
        'tax_rate' => 'tax rate',
        'tin' => 'TIN',
        'type' => 'type',
        'unit' => 'unit',
        'warehouse_id' => 'warehouse',
        'from_warehouse_id' => 'source warehouse',
        'to_warehouse_id' => 'destination warehouse',
    ],

];
