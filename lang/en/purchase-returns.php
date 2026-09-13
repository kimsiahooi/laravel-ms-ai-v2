<?php

declare(strict_types=1);

/*
| Purchase returns — goods going back to the supplier who delivered them, and what that credits.
|
| Words shared with the two order screens live in `orders.php`: a line, its discount, and what
| the document comes to. Words shared with sales returns live in `returns.php`: the three
| statuses and the five reasons. What is here is the half that belongs to sending goods back to
| a supplier.
|
| The vocabulary is deliberate in two places.
|
| "Remaining", not "available". A line's ceiling is about this delivery — how much of what
| arrived has not already been sent back — and has nothing to do with what is on the shelf. The
| shelf is a separate question, asked when the return is completed, and calling both of them
| "available" would make two different refusals read as one.
|
| "Credits", not "refunds". The return says what the goods were charged at; whether money
| actually comes back, and when, is between the workspace and its supplier, and this document
| does not pretend to know.
*/

return [
    'title' => 'Purchase returns',
    'subtitle' => 'Goods going back to a supplier, credited at what the delivery charged for them.',
    'search_placeholder' => 'Search return or order number, supplier or notes…',

    'column' => [
        'number' => 'Return',
        'order' => 'Against',
        'supplier' => 'Supplier',
        'status' => 'Status',
        'reason' => 'Reason',
        'total' => 'Credit',
        'created' => 'Raised',
    ],

    'action' => [
        'new' => 'New purchase return',
        'edit' => 'Edit return',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Any status',
        'reason' => 'Reason',
        'all_reasons' => 'Any reason',
    ],

    'create' => [
        'title' => 'Return against :number',
        'crumb' => 'New return',
        'subtitle' => 'Say how much of each delivered line is going back. The prices come from the order, so the credit matches what you were charged.',
        'submit' => 'Save return',
        'submitting' => 'Saving…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
        'subtitle' => 'Only a pending return can be changed.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    // The read-only block at the top of the form: the delivery being credited. Read-only
    // because changing it would invalidate every price and every line on the return, which is
    // the same work as raising a new one.
    'order' => [
        'heading' => 'Crediting',
        'number' => 'Purchase order',
        'supplier' => 'Supplier',
        'received' => 'Received',
        'locked' => 'A return credits one delivery, so this cannot be changed. Start a new return to credit a different order.',
    ],

    'field' => [
        'reason' => 'Reason',
        'reason_placeholder' => 'Why the goods are going back',
        'notes' => 'Notes',
        'notes_placeholder' => 'A reference, what the supplier said, anything worth remembering',
    ],

    // The grid. Every delivered line that still has something returnable appears; typing a
    // quantity puts it on the return, and leaving it blank leaves it off.
    'lines' => [
        'heading' => 'What is going back',
        'description' => 'Every line of the delivery that still has something returnable. Leave a quantity blank to keep that line off the return.',
        'fill_all' => 'Return everything',
        'empty' => 'Everything on this order has already been returned.',
    ],

    'line' => [
        'item' => 'Material',
        'ordered' => 'Delivered',
        'returned' => 'Returned',
        'remaining' => 'Remaining',
        'quantity' => 'Returning',
        'quantity_placeholder' => 'e.g. 2',
        'unit_cost' => 'Unit cost',
        'discount' => 'Discount',
        'total' => 'Line credit',
    ],

    'summary' => [
        'order' => 'Purchase order',
        'supplier' => 'Supplier',
        'currency' => 'Currency',
        'rate' => 'at :rate',
        'reason' => 'Reason',
        'raised_by' => 'Raised by',
        'notes' => 'Notes',
    ],

    'dialog' => [
        'delete' => [
            'title' => 'Delete :number?',
            'description' => 'The return is removed and the quantities on it become returnable again. Nothing has moved yet, so nothing is reversed.',
            'submit' => 'Delete return',
            'submitting' => 'Deleting…',
        ],
    ],

    'empty' => [
        'title' => 'No purchase returns yet',
        'description' => 'A return is raised against a delivery. Open the purchase order the goods came on and start one from there.',
        'action' => 'Go to received orders',
    ],

    // Reachable before anything has ever been received — a different nothing from the one
    // above, and the only advice that helps is "receive something first".
    'no_setup' => [
        'title' => 'Nothing has been received yet',
        'description' => 'Goods can only go back once they have arrived. Receive a purchase order and it becomes returnable.',
        'action' => 'Go to purchase orders',
    ],

    'no_match' => [
        'title' => 'No returns match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'toast' => [
        'created' => 'Purchase return raised.',
        'updated' => 'Purchase return updated.',
        'deleted' => 'Purchase return deleted.',
    ],

    'error' => [
        'not_pending' => 'This return has already been completed or cancelled.',
        'completed_locked' => 'A completed return cannot be changed or deleted.',
        'no_order' => 'That purchase order cannot be returned against — it may have been deleted, or it was never received.',
        'nothing_returnable' => 'Everything on that order has already been returned.',
    ],

    'validation' => [
        // Filed on the row's own quantity box by the FormRequest's after-hook and by the zod
        // gate, with the same number in both. Named rather than generic: the row already shows
        // delivered, returned and remaining, so the sentence worth reading is the ceiling.
        'over_return' => 'Only :remaining of this line can still be returned.',
    ],
];
