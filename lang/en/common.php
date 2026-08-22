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
        'row_actions' => 'Actions for :name',
    ],

    'list' => [
        'no_matches' => 'No matches',
        'no_matches_hint' => 'Nothing matched “:search”.',
        'page_empty' => 'Nothing on this page',
        'page_empty_hint' => 'Those rows are gone — the list may have got shorter since this page was opened.',
        'back_to_first' => 'Back to the first page',
        'actions_column' => 'Actions',
        'show' => 'Show',
        'rows_per_page' => 'Rows per page',
    ],

    'pagination' => [
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
        'days_ago' => ':count days ago',
    ],
];
