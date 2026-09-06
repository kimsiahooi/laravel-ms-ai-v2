<?php

declare(strict_types=1);

/*
| Strings shared by every screen: actions, list chrome, relative time. Anything
| that belongs to one area lives in that area's file instead.
*/

return [
    'actions' => [
        'cancel' => 'Cancel',
        'clear_search' => 'Clear search',
        'close' => 'Close',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'row_actions' => 'Actions for :name',
        'toggle_sidebar' => 'Toggle sidebar',
    ],

    // Marks a field the form will accept empty. Sits beside the label rather than
    // inside it, so the label stays the thing a screen reader announces.
    'field' => [
        'on_hand' => 'On hand now: :quantity',
        'none' => 'Not set',
        'optional' => '(optional)',
    ],

    // The shared confirm dialog. It is chrome, not console-specific — every module
    // from here on deletes something through it.
    'confirm' => [
        'type_to_confirm' => 'Type :phrase to confirm',
    ],

    // PasswordInput's reveal toggle. Also shared chrome: the sign-in screen shows it
    // long before anyone reaches account settings, which is where these strings
    // first, wrongly, lived.
    'password' => [
        'hide' => 'Hide password',
        'show' => 'Show password',
    ],

    'errors' => [
        'generic' => 'Something went wrong.',
    ],

    'filter' => [
        'trigger' => 'Filters',
        'description' => 'Narrow the list down to what you are looking for.',
        'clear' => 'Clear',
        'clear_all' => 'Clear all filters',
    ],

    'columns' => [
        'trigger' => 'Columns',
        'description' => 'Choose what this list shows, and drag to reorder.',
        'reset' => 'Reset to default',
        'hidden_count' => '{1} 1 hidden|[2,*] :count hidden',
        'move_up' => 'Move :column earlier',
        'move_down' => 'Move :column later',
        'drag' => 'Drag to reorder :column',
        'narrow_hidden' => 'Also hidden on narrow screens',
        'sorted_hint' => 'The list is sorted by this',
        'last_hint' => 'At least one column has to stay',
        'save_failed' => 'Could not save your columns. They will go back to normal when you reload.',
    ],

    'list' => [
        'no_matches' => 'No matches',
        'no_matches_filtered' => 'Nothing here matches the filters you have applied.',
        'no_matches_hint' => 'Nothing matched “:search”.',
        'page_empty' => 'Nothing on this page',
        'page_empty_hint' => 'Those rows are gone — the list may have got shorter since this page was opened.',
        'back_to_first' => 'Back to the first page',
        'actions_column' => 'Actions',
        'rows_per_page' => 'Rows per page',
    ],

    'pagination' => [
        'label' => 'Pagination',
        'page' => 'Page :page',
        'no_results' => 'No results',
        'showing' => 'Showing :from–:to of :total',
        'page_of' => 'Page :current of :last',
        'previous' => 'Previous',
        'next' => 'Next',
    ],

    'language' => [
        'change' => 'Change language',
    ],

    'theme' => [
        'change' => 'Change theme',
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    // Relative timestamps. lib/format.ts returns a key and a count rather than a
    // formatted string, so the wording lives here like everything else.
    'time' => [
        'just_now' => 'just now',
        'minutes_ago' => ':count min ago',
        'hours_ago' => ':count hr ago',
        'days_ago' => ':count day ago|:count days ago',
    ],
];
