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

    'email' => 'The :attribute field must be a valid email address.',
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',

    /*
     * What `:attribute` is replaced with. Laravel reads this automatically, which is
     * why no FormRequest needs an `attributes()` method — and the zod primitives read
     * the same keys, so a field is named identically on both sides of the wire.
     */
    'attributes' => [
        'admin_email' => 'administrator email',
        'admin_name' => 'administrator name',
        'admin_password' => 'administrator password',
        'name' => 'name',
        'slug' => 'slug',
    ],

];
