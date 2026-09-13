<?php

declare(strict_types=1);

/*
| Words shared by both kinds of return — purchase and sales alike. What lives here is what the
| two documents genuinely have in common: where a return is in its life, and why the goods are
| going back. Anything belonging to one side of the trade — who it is with, which direction the
| stock moves — belongs in that module's own file instead.
|
| The statuses are the whole vocabulary of the lifecycle, so they are worth being precise about.
| "Pending" is a return that has been raised and not yet acted on — the only state anything can
| still be done to. "Completed" and "Cancelled" are both terminal, and the screens say so rather
| than leaving somebody to discover it by pressing a button.
|
| The reasons are deliberately about the goods rather than about the trade, which is what lets
| one list serve both directions: a supplier sending something damaged and a customer sending
| something back damaged are the same fact. Wording that took a side — "changed their mind",
| "over-delivery" — would be wrong in one of the two modules.
*/

return [
    // App\Enums\ReturnStatus.
    'status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    // App\Enums\ReturnReason. Short, because they are read inside a select and again as a
    // column in a list — the heading above each already says "Reason".
    'reason' => [
        'damaged' => 'Damaged',
        'wrong_item' => 'Wrong item',
        'quality' => 'Not to specification',
        'surplus' => 'Surplus to needs',
        'other' => 'Other',
    ],
];
