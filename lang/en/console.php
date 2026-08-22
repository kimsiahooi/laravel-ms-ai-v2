<?php

declare(strict_types=1);

/*
| The central super-admin console at /admin. Not tenant-facing: these strings are
| only ever seen by a platform administrator.
*/

return [
    'name' => 'Console',
    'tagline' => 'Workspace administration',

    'nav' => [
        'group' => 'Manage',
        'overview' => 'Overview',
        'workspaces' => 'Workspaces',
        'archive' => 'Archive',
        'sign_out' => 'Sign out',
    ],

    'login' => [
        'title' => 'Console sign in',
        'heading' => 'Sign in to the console',
        'subheading' => 'Platform administrators only. To reach a workspace, use its own address instead.',
        'email' => 'Email address',
        'email_placeholder' => 'admin@example.com',
        'password' => 'Password',
        'password_placeholder' => 'Password',
        'remember' => 'Keep me signed in',
        'submit' => 'Sign in',
        'submitting' => 'Signing in…',
        'panel_heading' => 'Every workspace, in one place.',
        'panel_point_create' => 'Create a workspace and its first administrator in one step.',
        'panel_point_archive' => 'Archiving is reversible — a workspace keeps its data until it is permanently deleted.',
        'panel_footer' => 'Restricted to platform administrators.',
    ],

    'overview' => [
        'heading' => 'Overview',
        'subheading' => 'How the platform is being used.',
        'stat_live' => 'Live workspaces',
        'stat_today' => 'Added today',
        'stat_week' => 'Added this week',
        'stat_week_hint' => 'Rolling 7 days',
        'stat_archived' => 'Archived',
        'stat_archived_hint' => 'Restorable from the archive',
        'newest_title' => 'Most recent',
        'newest_description' => 'The last workspace to be created.',
        'newest_empty' => 'No workspaces yet.',
        'newest_created' => 'Created :when',
        'link_all' => 'All workspaces',
        'link_archive' => 'Archive',
        'trend_title' => 'New workspaces',
        'trend_empty' => 'None created in the last 30 days.',
        'trend_summary' => ':count workspace created in the last 30 days.|:count workspaces created in the last 30 days.',
    ],

    'workspaces' => [
        'title' => 'Workspaces',
        'heading' => 'Workspaces',
        'subheading' => 'Every customer workspace on this platform, each with its own database.',
        'view_archive' => 'View archive',
        'search_placeholder' => 'Search by name or address',
        'column_workspace' => 'Workspace',
        'column_address' => 'Address',
        'column_created' => 'Created',
        'empty_title' => 'No workspaces yet',
        'empty_description' => 'Create the first one — it gets its own database and an administrator who can sign in straight away.',
        'no_match_title' => 'No workspaces match that search',
        'no_match_description' => 'Nothing matched “:term”. Try part of the name, or the address from the URL.',
    ],

    'archive' => [
        'title' => 'Archived workspaces',
        'heading' => 'Archive',
        'subheading' => 'Archived workspaces are unreachable but intact. Their addresses stay reserved until they are deleted for good.',
        'back' => 'Back to workspaces',
        'search_placeholder' => 'Search the archive',
        'column_archived' => 'Archived',
        'empty_title' => 'The archive is empty',
        'empty_description' => 'Archived workspaces appear here, where they can be restored or permanently deleted.',
        'no_match_title' => 'Nothing in the archive matches',
        'no_match_description' => 'No archived workspace matched “:term”.',
    ],

    'create' => [
        'trigger' => 'New workspace',
        'title' => 'New workspace',
        'description' => 'Creates the workspace, its own database, and the first administrator who can sign in to it.',
        'name' => 'Workspace name',
        'name_placeholder' => 'Acme Trading',
        'slug' => 'Address',
        'slug_placeholder' => 'acme-trading',
        'slug_hint' => 'Lowercase letters, numbers and hyphens. This becomes the workspace’s sign-in address and cannot be changed later.',
        'admin_section' => 'First administrator',
        'admin_name' => 'Name',
        'admin_name_placeholder' => 'Jane Tan',
        'admin_email' => 'Email address',
        'admin_email_placeholder' => 'jane@acme.test',
        'admin_password' => 'Temporary password',
        'admin_password_placeholder' => 'At least 8 characters',
        'submit' => 'Create workspace',
        'submitting' => 'Creating…',
    ],

    'row' => [
        'open' => 'Open workspace',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'delete_forever' => 'Permanently delete :name',
    ],

    'confirm' => [
        'type_to_confirm' => 'Type :phrase to confirm',
        'archive_title' => 'Archive :name?',
        'archive_description' => 'Everyone signed in to this workspace loses access, but nothing is deleted — its database is untouched and you can restore it from the Archive at any time.',
        'archive_submit' => 'Archive workspace',
        'archive_submitting' => 'Archiving…',
        'restore_title' => 'Restore :name?',
        'restore_description' => 'The workspace becomes reachable again at its original address, with all of its data as it was.',
        'restore_submit' => 'Restore workspace',
        'restore_submitting' => 'Restoring…',
        'delete_title' => 'Permanently delete :name?',
        'delete_description' => 'This drops the workspace’s database and everything in it. There is no undo and no backup.',
        'delete_submit' => 'Delete permanently',
        'delete_submitting' => 'Deleting…',
    ],

    // Flashed from TenantController, so translated server-side by __().
    'toast' => [
        'created' => 'Workspace “:name” created — sign in at /:slug/login.',
        'archived' => 'Workspace “:name” archived. Its data is untouched.',
        'restored' => 'Workspace “:name” restored.',
        'deleted' => 'Workspace “:name” and its database were permanently deleted.',
    ],

    'validation' => [
        'slug_regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
        'slug_reserved' => 'That slug is reserved and cannot be used.',
        'slug_taken' => 'A workspace with that slug already exists (it may be archived — restore or permanently delete it first).',
        'slug_reserved_action' => 'The slug ":slug" is reserved and cannot be used.',
    ],
];
