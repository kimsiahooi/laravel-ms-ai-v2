<?php

declare(strict_types=1);

/*
| The permission catalog's own vocabulary — every screen a role can be granted, and the four
| things that can be done to one. Read only by the role editor.
|
| These names are a SECOND definition of words most modules already own, and that is a real
| cost worth stating rather than hiding. `lang/en/tenant.php` says a sidebar entry "names
| itself from its own module file, so 'Categories' has exactly one definition", and this file
| breaks that rule on purpose, because the alternative is worse: seven of the nineteen screens
| have no module file at all yet (the catalog is complete ahead of the screens it names), and
| `settings` here means the BUSINESS settings while `settings.title` is the account area — so
| composing `{screen}.title` in the browser would be wrong for seven screens and actively
| misleading for one.
|
| **So these must be kept in step by hand with the module titles they shadow.** Where a module
| file exists, the word below is copied from its `title` verbatim — including `locations` being
| "Sites" and `settings` being "Business settings", both of which read wrong if you go by the
| catalog key instead of by what the person actually sees in the sidebar.
|
| The verbs stand alone rather than being composed into "Edit categories": the checkbox sits
| inside a fieldset whose legend already names the screen, so a screen reader announces
| "Categories — Edit" out of markup. The catalog used to build that sentence in PHP with
| `lcfirst()` and a concatenation, which put the words in the wrong order for Malay and meant
| nothing at all in Chinese.
*/

return [
    // App\Enums\PermissionScreen.
    'screen' => [
        'categories' => 'Categories',
        'suppliers' => 'Suppliers',
        'customers' => 'Customers',
        'raw-materials' => 'Raw materials',
        'products' => 'Products',
        'locations' => 'Sites',
        'warehouses' => 'Warehouses',
        'stock-movements' => 'Stock movements',
        'stock-transfers' => 'Stock transfers',
        'stock-takes' => 'Stock takes',
        'purchase-orders' => 'Purchase orders',
        'purchase-returns' => 'Purchase returns',
        'sales-orders' => 'Sales orders',
        'sales-returns' => 'Sales returns',
        'reports' => 'Reports',
        'activity' => 'Activity',
        'users' => 'Users',
        'roles' => 'Roles',
        'settings' => 'Business settings',
    ],

    // App\Enums\PermissionAction. "Edit" rather than "Update", because that is the word the
    // rest of the app puts on the button.
    'action' => [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ],
];
