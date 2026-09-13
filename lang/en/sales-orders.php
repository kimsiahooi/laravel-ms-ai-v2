<?php

declare(strict_types=1);

/*
| Sales orders — what the workspace has sold to a customer, and what happened when it
| shipped.
|
| Words shared with purchase orders live in `orders.php`: a line, its discount, and what the
| document comes to. What is here is the half that belongs to selling — the counterparty is a
| customer, the money on a line is a price rather than a cost, and the ending is goods
| leaving a warehouse.
|
| The three statuses are the whole vocabulary of the module, so they are worth being precise
| about. "Pending" is an order that has been taken and not yet shipped — the only state
| anything can still be done to. "Fulfilled" and "Cancelled" are both terminal, and the
| screens say so rather than leaving somebody to discover it by pressing a button.
*/

return [
    'title' => 'Sales orders',
    'subtitle' => 'What your customers have ordered, and what it comes to. Fulfilling one takes the goods out of a warehouse.',
    'search_placeholder' => 'Search order number, customer or notes…',

    'column' => [
        'number' => 'Order',
        'customer' => 'Customer',
        'status' => 'Status',
        'total' => 'Total',
        'expected' => 'Promised',
        'created' => 'Taken',
    ],

    // App\Enums\SalesOrderStatus.
    'status' => [
        'pending' => 'Pending',
        'fulfilled' => 'Fulfilled',
        'cancelled' => 'Cancelled',
    ],

    'action' => [
        'new' => 'New sales order',
        'edit' => 'Edit order',
        'cancel' => 'Cancel order',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Any status',
        'customer' => 'Customer',
        'all_customers' => 'Any customer',
        'customer_search' => 'Search customers…',
        'customer_empty' => 'No customers match.',
    ],

    'create' => [
        'title' => 'New sales order',
        'crumb' => 'New order',
        'subtitle' => 'Who you are selling to, what they are buying, and what you agreed to charge. The number is assigned when the order is saved.',
        'submit' => 'Save order',
        'submitting' => 'Saving…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
    ],

    'lines' => [
        'heading' => 'What is being sold',
    ],

    'field' => [
        'customer' => 'Customer',
        'customer_placeholder' => 'Who you are selling to',
        'customer_search' => 'Search customers…',
        'customer_empty' => 'No customers match.',
        'currency' => 'Currency',
        'currency_placeholder' => 'Choose a currency',
        'exchange_rate' => 'Exchange rate',
        'exchange_rate_placeholder' => 'e.g. 4.35',
        'exchange_rate_hint' => 'How much of your own currency one unit of the order currency is worth, on the day the order was agreed.',
        'expected_date' => 'Promised delivery',
        'expected_date_hint' => 'The day the customer expects the goods, and the time if one was agreed. Used for planning only — nothing happens on it.',
        'notes' => 'Notes',
        'notes_placeholder' => 'Terms, a purchase order reference, anything worth remembering',
    ],

    // The read-only document. "Unit price", not the purchase side's "Unit cost": this side
    // of the trade is money coming in.
    'line' => [
        'item' => 'Item',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit price',
        'discount' => 'Discount',
        'total' => 'Line total',
    ],

    'summary' => [
        'customer' => 'Customer',
        'currency' => 'Currency',
        'rate' => 'at :rate',
        'expected' => 'Promised delivery',
        'raised_by' => 'Taken by',
        'fulfilled_by' => 'Shipped by',
        'fulfilled_at' => 'Shipped',
        'fulfilled_from' => 'Shipped from',
        'notes' => 'Notes',
    ],

    'dialog' => [
        'cancel' => [
            'title' => 'Cancel this order?',
            'description' => 'The order is closed and no stock is moved. You cannot reopen a cancelled order, or ship against it later.',
            'submit' => 'Cancel order',
            'submitting' => 'Cancelling…',
        ],
    ],

    'empty' => [
        'title' => 'No sales orders yet',
        'description' => 'Take one to record what a customer has ordered and what you agreed to charge for it.',
    ],

    'no_match' => [
        'title' => 'No orders match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'no_setup' => [
        'title' => 'Add a customer first',
        'description' => 'An order is taken from somebody, and there is nobody to take one from yet.',
        'action' => 'Go to customers',
    ],

    'toast' => [
        'created' => 'Sales order taken.',
        'updated' => 'Sales order updated.',
        'cancelled' => 'Sales order cancelled.',
        'deleted' => 'Sales order deleted.',
    ],

    'error' => [
        'not_pending' => 'This order has already been fulfilled or cancelled.',
        'fulfilled_locked' => 'A fulfilled order cannot be changed or deleted.',
    ],
];
