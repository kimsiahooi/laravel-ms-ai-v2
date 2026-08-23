<?php

declare(strict_types=1);

/*
| The central landing page: a workspace picker. Every route is prefixed /{tenant}/, so
| there is no single sign-in URL — this turns a workspace name into one.
*/

return [
    'head' => 'Welcome',
    'title' => 'Inventory',
    'subtitle' => 'Enter your workspace to sign in.',
    'workspace' => 'Workspace',
    'hint' => 'The name in your address bar, e.g.',
    'submit' => 'Continue',
];
