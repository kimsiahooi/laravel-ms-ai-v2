<?php

declare(strict_types=1);

/*
| Sites — the first stock module, and the top of the storage hierarchy. One screen: a
| list, a dialog over it, and a confirmation. Chrome it shares with every other list
| (search, paging, Cancel, Edit, Delete) lives in common.php.
*/

return [
    'title' => 'Sites',
    'subtitle' => 'The places you operate from. Each one holds the warehouses that stock actually lives in.',

    'search_placeholder' => 'Search name, code or address…',

    'column' => [
        'name' => 'Site',
        'code' => 'Code',
        'address' => 'Address',
        'created' => 'Added',
        'creator' => 'Added by',
    ],

    'empty' => [
        'title' => 'No sites yet',
        'description' => 'A site is a branch, an outlet or a factory. Add the first one and you can start giving it warehouses.',
    ],

    'no_match' => [
        'title' => 'No sites match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New site',
        'title' => 'New site',
        'description' => 'Where you operate from. Only the name is required.',
        'submit' => 'Create site',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit site',
        'description' => 'Changes apply everywhere this site is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. Penang branch',
        'code' => 'Code',
        'code_placeholder' => 'e.g. PEN',
        'code_hint' => 'Your own short code for this site. It appears on transfers and reports, and no two sites can share one.',
        'address' => 'Address',
        'address_placeholder' => 'Street, town, postcode',
    ],

    'confirm' => [
        'blocked_title' => 'Cannot delete :name',
        'blocked_description' => '{1} A warehouse still stands on this site: :warehouses. Move or remove it first, then the site can be deleted.|[2,*] :count warehouses still stand on this site (:warehouses). Move or remove them first, then the site can be deleted.',
        'blocked_link' => '{1} View this warehouse|[2,*] View all :count warehouses',
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Movements already recorded at this site keep their history — you simply will not be able to pick it for anything new.',
        'delete_submit' => 'Delete site',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'in_use' => '{1} :name cannot be deleted — a warehouse still stands on it: :warehouses.|[2,*] :name cannot be deleted — :count warehouses still stand on it (:warehouses).',
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
