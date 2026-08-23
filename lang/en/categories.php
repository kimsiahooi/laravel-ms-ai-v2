<?php

declare(strict_types=1);

/*
| Product categories — the first catalog module. One screen: a list, a dialog over
| it, and a confirmation. Chrome it shares with every other list (search, paging,
| Cancel, Edit, Delete) lives in common.php.
*/

return [
    // Used by the sidebar, the breadcrumb, the browser tab and the heading — one name
    // for the module, so they cannot drift apart.
    'title' => 'Categories',
    'subtitle' => 'Group the products in your catalogue so a long list stays findable.',

    'search_placeholder' => 'Search name or description…',

    'column' => [
        'name' => 'Name',
        'description' => 'Description',
        'created' => 'Created',
        'creator' => 'Created by',
    ],

    'empty' => [
        'title' => 'No categories yet',
        'description' => 'Categories group your products. Create the first one and it will be waiting when you add a product.',
    ],

    'no_match' => [
        'title' => 'No categories match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New category',
        'title' => 'New category',
        'description' => 'Name a group your products will be filed under.',
        'submit' => 'Create category',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit category',
        'description' => 'Renaming a category updates it everywhere it is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. Fasteners',
        'description' => 'Description',
        'description_placeholder' => 'What belongs in this category',
    ],

    'confirm' => [
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Products already filed under this category keep their data — they simply stop being grouped by it. Nothing else is removed.',
        'delete_submit' => 'Delete category',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
