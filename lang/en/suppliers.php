<?php

declare(strict_types=1);

/*
| Suppliers — who the workspace buys from. Chrome shared with every other list
| (search, paging, Cancel, Edit, Delete) lives in common.php.
*/

return [
    'title' => 'Suppliers',
    'subtitle' => 'Who you buy from, and how to reach them.',

    'search_placeholder' => 'Search name, contact person, phone, email or notes…',

    'column' => [
        'name' => 'Supplier',
        'email' => 'Email',
        'phone' => 'Phone',
        'created' => 'Added',
        'creator' => 'Added by',
    ],

    'empty' => [
        'title' => 'No suppliers yet',
        'description' => 'Add the first one and it will be ready to pick when you raise a purchase order.',
    ],

    'no_match' => [
        'title' => 'No suppliers match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New supplier',
        'title' => 'New supplier',
        'description' => 'Only the name is required — the rest can wait until you have it.',
        'submit' => 'Create supplier',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit supplier',
        'description' => 'Changes apply everywhere this supplier is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Company name',
        'name_placeholder' => 'e.g. Acme Steel Sdn Bhd',
        'contact_person' => 'Contact person',
        'contact_person_placeholder' => 'Who you deal with',
        'email' => 'Email',
        'email_placeholder' => 'orders@example.com',
        'phone' => 'Phone',
        'phone_placeholder' => '+60 3 1234 5678',
        'tax_id' => 'Tax ID',
        'tax_id_placeholder' => 'Registration or SST number',
        'address' => 'Address',
        'address_placeholder' => 'Where deliveries and invoices go',
        'notes' => 'Notes',
        'notes_placeholder' => 'Payment terms, lead times, anything worth remembering',
    ],

    'confirm' => [
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Purchase orders already raised against this supplier keep their record of it — you simply will not be able to pick them for a new one.',
        'delete_submit' => 'Delete supplier',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
