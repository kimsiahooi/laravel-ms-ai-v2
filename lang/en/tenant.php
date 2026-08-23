<?php

declare(strict_types=1);

/*
| The workspace shell — chrome a signed-in tenant user sees on every screen. Module
| strings live in that module's own file.
*/

return [
    // Fallback for the workspace's own name, used only before one is known.
    'name' => 'Workspace',

    'nav' => [
        // Sidebar group headings only. An entry names itself from its own module
        // file, so 'Categories' has exactly one definition.
        'catalog' => 'Catalog',
        'dashboard' => 'Dashboard',
        'settings' => 'Account settings',
        'sign_out' => 'Sign out',
    ],
];
