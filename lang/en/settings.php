<?php

declare(strict_types=1);

/*
| Account settings — the screens under /{tenant}/settings and the components they
| pull in (passkeys, two-factor, account deletion).
|
| These belong to the PERSON, not to the workspace, which is why they live apart from
| tenant.php and are reached from the user menu rather than the sidebar.
*/

return [
    'nav' => [
        'profile' => 'Profile',
        'security' => 'Security',
        'appearance' => 'Appearance',
    ],

    'heading' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account settings',
        'nav_label' => 'Settings sections',
    ],

    'profile' => [
        'head' => 'Profile settings',
        'title' => 'Profile',
        'description' => 'Update your name and email address',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'email_placeholder' => 'Email address',
        'unverified' => 'Your email address is unverified.',
        'resend' => 'Click here to re-send the verification email.',
        'sent' => 'A new verification link has been sent to your email address.',
        'save' => 'Save',
    ],

    'security' => [
        'head' => 'Security settings',
        'title' => 'Update password',
        'description' => 'Use a long, random password to keep your account secure',
        'current' => 'Current password',
        'new' => 'New password',
        'confirm' => 'Confirm password',
        'save' => 'Save',
    ],

    'appearance' => [
        'head' => 'Appearance settings',
        'title' => 'Appearance',
        'description' => 'Choose how the app looks',
        'theme_scope' => 'Applies to this device only.',
    ],

    'columns' => [
        'title' => 'Table columns',
        'description' => 'Which columns each list shows, and in what order. Follows your account, on every device.',
        'customised' => '{0} No list has been changed — every one is showing its usual columns.|{1} One list is showing something other than its usual columns.|[2,*] :count lists are showing something other than their usual columns.',
        'action' => 'Reset every list',
        'confirm_title' => 'Reset every list?',
        'confirm_description' => 'Every list goes back to the columns it started with, in their original order. Nothing else about your account changes, and you can rearrange them again at any time.',
        'confirm_action' => 'Reset every list',
        'confirming' => 'Resetting…',
        'toast' => 'Every list is back to its usual columns.',
    ],

    'delete' => [
        'title' => 'Delete account',
        'description' => 'Delete your account and all of its data',
        'warning' => 'Warning',
        'warning_body' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'confirm_title' => 'Are you sure you want to delete your account?',
        'confirm_body' => 'Once your account is deleted, all of its data is permanently deleted with it. Enter your password to confirm.',
    ],

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Add a second step to signing in',
        'enabled_body' => 'You will be asked for a code when you sign in. Get it from the authenticator app on your phone.',
        'disabled_body' => 'Once enabled, you will be asked for a code when you sign in. The code comes from an authenticator app on your phone.',
        'disable' => 'Disable',
        'continue_setup' => 'Continue setup',
        'enable' => 'Enable',
    ],

    'passkeys' => [
        'removing' => 'Removing…',
        'register' => 'Register passkey',
        'registering' => 'Registering…',
        'title' => 'Passkeys',
        'description' => 'Sign in without a password',
        'empty_title' => 'No passkeys yet',
        'empty_body' => 'Add one to sign in without a password.',
        'add' => 'Add passkey',
        'name' => 'Passkey name',
        'name_placeholder' => 'e.g. MacBook Pro, iPhone',
        'name_hint' => 'A name helps you identify this passkey later.',
        'unsupported' => 'Passkeys are not supported in this browser.',
        'remove' => 'Remove',
        'remove_title' => 'Remove passkey',
        'remove_body' => 'Remove the “:name” passkey? You will no longer be able to sign in with it.',
        'added' => 'Added :when',
        'last_used' => 'Last used :when',
    ],

    'recovery' => [
        'title' => 'Recovery codes',
        'body' => 'Recovery codes let you back in if you lose your authenticator. Keep them in a password manager.',
        'view_codes' => 'View recovery codes',
        'hide_codes' => 'Hide recovery codes',
        'regenerate' => 'Regenerate codes',
        'note' => 'Each code works once and disappears after use. If you run out, regenerate them above.',
    ],

    'setup' => [
        'enable_title' => 'Enable two-factor authentication',
        'enable_description' => 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
        'verify_title' => 'Verify authentication code',
        'verify_description' => 'Enter the 6-digit code from your authenticator app',
        'enabled_title' => 'Two-factor authentication enabled',
        'enabled_description' => 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
        'continue' => 'Continue',
        'manual' => 'or enter the code manually',
        'back' => 'Back',
        'confirm' => 'Confirm',
    ],
];
