<?php

declare(strict_types=1);

/*
| Sales returns — goods a customer sent back, and what that credits.
|
| Words shared with the two order screens live in `orders.php`: a line, its discount, the
| availability panel, and what the document comes to. Words shared with purchase returns live
| in `returns.php`: the three statuses and the five reasons. What is here is the half that
| belongs to taking goods back from a customer.
|
| The mirror of `purchase-returns.php`, and deliberately not a copy of it: a return to a
| supplier *sends* and a return from a customer *receives*, so the verbs differ throughout even
| where the structure does not.
*/

return [
    'title' => 'Sales returns',
    'subtitle' => 'Goods a customer sent back, credited at what they were charged for them.',
    'search_placeholder' => 'Search return or order number, customer or notes…',

    'column' => [
        'number' => 'Return',
        'order' => 'Against',
        'customer' => 'Customer',
        'status' => 'Status',
        'reason' => 'Reason',
        'total' => 'Credit',
        'created' => 'Raised',
    ],

    'action' => [
        'new' => 'New sales return',
        'edit' => 'Edit return',
        'complete' => 'Complete return',
        'cancel' => 'Cancel return',
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
        'subtitle' => 'Say how much of each despatched line came back. The prices come from the order, so the credit matches what the customer was charged.',
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

    'order' => [
        'heading' => 'Crediting',
        'number' => 'Sales order',
        'customer' => 'Customer',
        'fulfilled' => 'Despatched',
        'locked' => 'A return credits one despatch, so this cannot be changed. Start a new return to credit a different order.',
    ],

    'field' => [
        'reason' => 'Reason',
        'reason_placeholder' => 'Why the goods came back',
        'notes' => 'Notes',
        'notes_placeholder' => 'A reference, what the customer said, anything worth remembering',
    ],

    'lines' => [
        'heading' => 'What came back',
        'description' => 'Every line of the despatch that still has something returnable. Leave a quantity blank to keep that line off the return.',
        'fill_all' => 'Return everything',
        'empty' => 'Everything on this order has already been returned.',
    ],

    'line' => [
        'item' => 'Product',
        'sold' => 'Despatched',
        'returned' => 'Returned',
        'remaining' => 'Remaining',
        'quantity' => 'Returning',
        'quantity_placeholder' => 'e.g. 2',
        'unit_price' => 'Unit price',
        'discount' => 'Discount',
        'total' => 'Line credit',
    ],

    // The card that puts the goods back. Its own block rather than keys under `action`,
    // because it is a heading, a sentence and a picker, not a button. There is no availability
    // panel under it and there never will be: putting stock back cannot run short.
    'complete' => [
        'heading' => 'Taking the goods back in',
        'description' => 'Completing the return puts every line back into one warehouse and closes it. Choose where the goods are actually going.',
        'warehouse' => 'Receive into',
        'warehouse_placeholder' => 'Choose a warehouse',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'no_warehouses' => 'There is nowhere to receive this into yet.',
        'no_warehouses_action' => 'Set up a warehouse',
    ],

    'summary' => [
        'order' => 'Sales order',
        'customer' => 'Customer',
        'currency' => 'Currency',
        'rate' => 'at :rate',
        'reason' => 'Reason',
        'raised_by' => 'Raised by',
        'completed_by' => 'Completed by',
        'completed_at' => 'Completed',
        'warehouse' => 'Received into',
        'notes' => 'Notes',
    ],

    'dialog' => [
        'complete' => [
            'title' => 'Complete this return?',
            // Plural, because "All 1 lines" is what a single-line return reads as otherwise.
            'description' => '{1}One line goes back into :warehouse and the return is closed. Stock moves as soon as you confirm, and this cannot be undone.|[2,*]All :count lines go back into :warehouse and the return is closed. Stock moves as soon as you confirm, and this cannot be undone.',
            'submit' => 'Complete return',
            'submitting' => 'Completing…',
        ],
        'cancel' => [
            'title' => 'Cancel this return?',
            'description' => 'The return is closed and no stock is moved. The quantities on it become returnable again. You cannot reopen a cancelled return.',
            'submit' => 'Cancel return',
            'submitting' => 'Cancelling…',
        ],
        'delete' => [
            'title' => 'Delete :number?',
            'description' => 'The return is removed and the quantities on it become returnable again. Nothing has moved yet, so nothing is reversed.',
            'submit' => 'Delete return',
            'submitting' => 'Deleting…',
        ],
    ],

    'empty' => [
        'title' => 'No sales returns yet',
        'description' => 'A return is raised against a despatch. Open the sales order the goods went out on and start one from there.',
        'action' => 'Go to despatched orders',
    ],

    'no_setup' => [
        'title' => 'Nothing has shipped yet',
        'description' => 'Goods can only come back once they have gone out. Fulfil a sales order and it becomes returnable.',
        'action' => 'Go to sales orders',
    ],

    'no_match' => [
        'title' => 'No returns match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'toast' => [
        'created' => 'Sales return raised.',
        'updated' => 'Sales return updated.',
        'deleted' => 'Sales return deleted.',
        'completed' => 'Sales return completed. The stock has moved.',
        'cancelled' => 'Sales return cancelled.',
    ],

    'error' => [
        'not_pending' => 'This return has already been completed or cancelled.',
        'completed_locked' => 'A completed return cannot be changed or deleted.',
        'no_order' => 'That sales order cannot be returned against — it may have been deleted, or it was never despatched.',
        'nothing_returnable' => 'Everything on that order has already been returned.',
        // There is no `short` key here and there should not be: putting stock back cannot run
        // short. This one covers the lock path the service declares and the Action cannot
        // reach — see the controller.
        'short_raced' => 'Somebody moved this stock while the return was completing. Nothing was put back — check the figures and try again.',
        // The only thing that can refuse a completion on this side: another return got there
        // first. No warehouse fixes it, so editing this one down is the way out.
        'over_return_now' => '{1}Another return has been completed since this one was raised. Only :remaining of :item can still come back, and this return is bringing :requested. Edit it and try again.|[2,*]Another return has been completed since this one was raised, and :count of these lines no longer fit the despatch. Edit the return and try again.',
    ],

    'validation' => [
        'over_return' => 'Only :remaining of this line can still be returned.',
    ],
];
