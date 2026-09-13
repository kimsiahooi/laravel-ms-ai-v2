<?php

declare(strict_types=1);

/*
| Roles — what a set of people can reach, and the screen where that is decided.
|
| The words for the screens themselves are not here. They live in `permissions.php`, keyed by
| the catalog's own values, and the editor composes `permissions.screen.{value}` and
| `permissions.action.{value}` per checkbox. What is here is the vocabulary of the role: its
| name, the two counts that describe it, and the sentences said when one cannot be changed.
|
| A role's own name is never translated, and that is worth saying out loud because it looks
| like an omission. "Administrator" is seeded, but every other role is a word this workspace
| typed — there is no locale in which "Stock clerk" becomes something else unless somebody
| retypes it.
|
| Both counts are plural forms rather than a number beside a fixed noun, because Malay and
| Chinese do not inflect and English does; `{0}` is a real case for each, and it is the one
| worth writing well — a role nobody holds is exactly the role somebody is about to delete.
*/

return [
    'title' => 'Roles',
    'subtitle' => 'A role decides which screens the people holding it can reach. Give somebody the narrowest one that still lets them do their job.',

    'action' => [
        'new' => 'New role',
    ],

    'card' => [
        'built_in' => 'Built in',
        'locked' => 'Holds everything, always. It cannot be edited or deleted, so a workspace can never lock itself out.',
        'permissions' => '{0}Grants nothing|{1}1 permission|[2,*]:count permissions',
        'holders' => '{0}Nobody holds it|{1}1 person holds it|[2,*]:count people hold it',
    ],

    'create' => [
        'title' => 'New role',
        'crumb' => 'New role',
        'subtitle' => 'Name it after the job somebody does, then tick what that job needs to reach.',
        'submit' => 'Create role',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'crumb' => 'Edit',
        'subtitle' => 'Changes apply the next time each person loads a page.',
        // Said on the form rather than only on the list, because it is the stake: editing a
        // role changes what these people can do, and the number should be in front of you
        // while you untick something.
        'holders' => '{0}Nobody holds this role yet.|{1}One person holds this role.|[2,*]:count people hold this role.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Role name',
        'name_placeholder' => 'e.g. Stock clerk',
        'name_hint' => 'The job, not the person. This is what the Users screen offers when somebody is given a role.',
    ],

    'matrix' => [
        'heading' => 'What this role reaches',
        'description' => 'Each group is one screen. “View” is what puts it in somebody’s sidebar at all — without it, the rest of the group has nothing to act on.',
        'select_all' => 'Select all',
        'clear_all' => 'Clear all',
        // The accessible name of a group's own tick-everything box, which shows no text of
        // its own: the legend beside it already names the screen.
        'group_all' => 'Select everything in :screen',
        'group_count' => ':selected of :total',
        'selected' => ':selected of :total selected',
    ],

    'dialog' => [
        'delete' => [
            'title' => 'Delete :name?',
            'description' => 'The role is removed for good. Nobody holds it, so nobody loses access — but anything you had ticked here would have to be ticked again on a new one.',
            'submit' => 'Delete role',
            'submitting' => 'Deleting…',
        ],
    ],

    'toast' => [
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],

    'error' => [
        // The count includes deactivated colleagues, who still hold their role and need it
        // back when they are reactivated — so it can legitimately be larger than the number
        // of people visible on the Users screen. Saying so is cheaper than being asked.
        'in_use' => '{1}:name is still held by one person, deactivated colleagues included. Move them to another role first.|[2,*]:name is still held by :count people, deactivated colleagues included. Move them to another role first.',
    ],

    'validation' => [
        // Replaces "The permissions field is required", which is true and reads like a
        // missing text box rather than a grid nobody has ticked.
        'permissions' => 'Choose at least one thing this role can reach.',
    ],
];
