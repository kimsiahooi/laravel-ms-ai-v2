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
        'dashboard' => 'Dashboard',
        'settings' => 'Account settings',
        'sign_out' => 'Sign out',
    ],
];
