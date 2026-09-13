<?php

declare(strict_types=1);

/*
| Users — who may sign in to this workspace, and what each of them can reach.
|
| The vocabulary is deliberate in two places.
|
| "Deactivate", never "delete". The row survives: orders name the person who raised them, and
| an order that cannot say who took it is not a record. What an administrator actually wants is
| for somebody to stop being able to sign in, and that is what the word should say.
|
| "Temporary password", said out loud on the list. A person added here starts with a password
| their administrator chose and therefore knows, which is a state that should look unfinished
| until they replace it. See the must_change_password migration for why the app takes that
| trade rather than emailing an invitation.
*/

return [
    'title' => 'Users',
    'subtitle' => 'Who can sign in to this workspace, and the role that decides what they reach.',
    'search_placeholder' => 'Search name or email…',

    'column' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'status' => 'Status',
        'created' => 'Added',
    ],

    'status' => [
        'active' => 'Active',
        'deactivated' => 'Deactivated',
        // Not an error — a stage. They have been added and have not finished joining.
        'temporary_password' => 'Temporary password',
    ],

    'filter' => [
        'status' => 'Status',
        'active' => 'Active',
        'deactivated' => 'Deactivated',
        'all' => 'Everyone',
    ],

    'action' => [
        'new' => 'Add someone',
        'edit' => 'Edit',
        'deactivate' => 'Deactivate',
        'restore' => 'Reactivate',
    ],

    'create' => [
        'title' => 'Add someone',
        'description' => 'They sign in with the password you set here, and are asked to replace it the first time they do.',
        'submit' => 'Add user',
        'submitting' => 'Adding…',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'description' => 'Leave the password blank to keep theirs. Setting one asks them to replace it again.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Name',
        'name_placeholder' => 'Their full name',
        'email' => 'Email',
        'email_placeholder' => 'name@company.com',
        'email_hint' => 'This is what they sign in with.',
        'role' => 'Role',
        'role_placeholder' => 'What they can reach',
        'role_search' => 'Search roles…',
        'role_empty' => 'No roles match.',
        'password' => 'Temporary password',
        'password_placeholder' => 'At least 8 characters',
        'password_hint' => 'You will need to tell them this. They are asked to replace it on their first sign-in.',
        'password_optional_hint' => 'Leave blank to keep their current password.',
        'password_confirmation' => 'Confirm password',
    ],

    'dialog' => [
        'deactivate' => [
            'title' => 'Deactivate :name?',
            'description' => 'They stop being able to sign in. Everything they have already done keeps their name on it, and you can reactivate them at any time.',
            'submit' => 'Deactivate',
            'submitting' => 'Deactivating…',
        ],
    ],

    'empty' => [
        'title' => 'Just you so far',
        'description' => 'Add a colleague and give them a role that reaches only the part of the workspace they need.',
    ],

    'no_match' => [
        'title' => 'Nobody matches',
        'description' => 'Nothing here matches “:term”.',
    ],

    'toast' => [
        'created' => ':name can now sign in.',
        'updated' => ':name updated.',
        'deactivated' => ':name can no longer sign in.',
        'restored' => ':name can sign in again.',
    ],

    'error' => [
        // The lockout invariant, in the three places somebody can run into it.
        'last_administrator' => 'This workspace needs at least one administrator, and they are the only one left.',
        'last_administrator_self' => 'You are the only administrator. Give somebody else the Administrator role before deleting your account, or the workspace will have nobody who can fix anything.',
        'not_yourself' => 'You cannot deactivate your own account.',
        'must_change_password' => 'Choose your own password before going any further.',
    ],

    'validation' => [
        // Replaces "has already been taken", which is true and sends somebody looking for an
        // account that is not in the list they are staring at.
        'email_deactivated' => 'That address belongs to a deactivated person. Reactivate them instead of adding a new account.',
        'last_administrator' => 'They are the only administrator. Give somebody else that role first.',
    ],
];
