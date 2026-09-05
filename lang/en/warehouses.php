<?php

declare(strict_types=1);

/*
| Warehouses — the buildings stock sits in. One screen: a list, a dialog over it, and
| a confirmation. Chrome shared with every other list lives in common.php.
*/

return [
    'title' => 'Warehouses',
    'subtitle' => 'The buildings your stock sits in. Each one belongs to a site.',

    'search_placeholder' => 'Search name, code or address…',

    'filter' => [
        'site' => 'Site',
        'all_sites' => 'Any site',
        'sites_selected' => 'Any of :count sites',
        'site_hint' => '[0,1] Tick more than one to widen the search — a warehouse need only be on one of them.|[2,*] Showing warehouses on any of these :count sites, not warehouses somehow on all of them.',
        'site_search' => 'Search sites…',
        'site_empty' => 'No sites match.',
    ],

    'column' => [
        'name' => 'Warehouse',
        'code' => 'Code',
        'site' => 'Site',
        'address' => 'Address',
        'created' => 'Added',
        'creator' => 'Added by',
        'view_site' => 'View :name in sites',
    ],

    'empty' => [
        'title' => 'No warehouses yet',
        'description' => 'A warehouse is where stock actually sits. Add the first one and you can start moving stock into it.',
    ],

    'no_match' => [
        'title' => 'No warehouses match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'no_sites' => [
        'title' => 'Add a site first',
        'description' => 'Every warehouse belongs to a site, so there is nothing to attach one to yet.',
        'action' => 'Go to sites',
    ],

    'create' => [
        'trigger' => 'New warehouse',
        'title' => 'New warehouse',
        'description' => 'Where stock is kept, and the site it stands on.',
        'submit' => 'Create warehouse',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit warehouse',
        'description' => 'Changes apply everywhere this warehouse is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'site' => 'Site',
        'site_placeholder' => 'Choose a site',
        'site_search' => 'Search sites…',
        'site_empty' => 'No sites match.',
        'site_hint' => 'The place this building stands. Moving a warehouse between sites moves its stock with it.',
        'name' => 'Name',
        'name_placeholder' => 'e.g. Main store',
        'code' => 'Code',
        'code_placeholder' => 'e.g. PEN-A',
        'code_hint' => 'Your own short code for this warehouse. It appears on transfers and reports, and no two warehouses can share one.',
        'address' => 'Address',
        'address_placeholder' => 'Street, town, postcode',
    ],

    'confirm' => [
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Movements already recorded through this warehouse keep their history — you simply will not be able to pick it for anything new.',
        'delete_submit' => 'Delete warehouse',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
