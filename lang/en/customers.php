<?php

declare(strict_types=1);

/*
| Customers — who the workspace sells to. Wider than suppliers because an invoice has
| to be addressed to a legal entity: see the tax and address groups.
*/

return [
    'title' => 'Customers',
    'subtitle' => 'Who you sell to, and the details an invoice has to carry.',

    'search_placeholder' => 'Search name, contact, email, TIN or notes…',

    'column' => [
        'name' => 'Customer',
        'email' => 'Email',
        'location' => 'Location',
        'created' => 'Added',
        'creator' => 'Added by',
    ],

    'empty' => [
        'title' => 'No customers yet',
        'description' => 'Add the first one and it will be ready to pick when you raise a sales order.',
    ],

    'no_match' => [
        'title' => 'No customers match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New customer',
        'title' => 'New customer',
        'description' => 'Only the name is required. The tax and address details are for invoicing, and can wait until you have them.',
        'submit' => 'Create customer',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit customer',
        'description' => 'Changes apply everywhere this customer is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'group' => [
        'identity' => 'Who they are',
        'tax' => 'Tax identity',
        'tax_hint' => 'Required on an e-invoice, optional here — fill it in when you have it.',
        'address' => 'Billing address',
    ],

    'field' => [
        'name' => 'Company name',
        'name_placeholder' => 'e.g. Meridian Engineering Sdn Bhd',
        'contact_person' => 'Contact person',
        'contact_person_placeholder' => 'Who you deal with',
        'email' => 'Email',
        'email_placeholder' => 'accounts@example.com',
        'phone' => 'Phone',
        'phone_placeholder' => '+60 3 1234 5678',
        'tin' => 'TIN',
        'tin_placeholder' => 'Tax identification number',
        'registration_no' => 'Registration number',
        'registration_no_placeholder' => 'SSM (MY) or UEN (SG)',
        'sst_registration_no' => 'SST / GST number',
        'sst_registration_no_placeholder' => 'If they are registered',
        'address' => 'Street address',
        'address_placeholder' => 'Building, street, unit',
        'city' => 'City',
        'city_placeholder' => 'e.g. Shah Alam',
        'postcode' => 'Postcode',
        'postcode_placeholder' => 'e.g. 40150',
        'state_code' => 'State code',
        'state_code_placeholder' => 'e.g. 10',
        'country_code' => 'Country',
        'country_code_placeholder' => 'Choose a country',
        'notes' => 'Notes',
        'notes_placeholder' => 'Credit terms, delivery instructions, anything worth remembering',
    ],

    'confirm' => [
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Sales orders and invoices already raised for this customer keep their record of it — you simply will not be able to pick them for a new one.',
        'delete_submit' => 'Delete customer',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
