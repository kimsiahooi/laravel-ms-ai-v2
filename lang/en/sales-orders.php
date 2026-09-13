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
        'fulfil' => 'Fulfil',
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
        // A sales order sells finished products and nothing else — see the purchase side.
        'item_placeholder' => 'Choose a product',
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

    'fulfil' => [
        'heading' => 'Fulfilling',
        'description' => 'Shipping the order takes every line out of one warehouse and closes it. Choose where the goods actually left from.',
        'warehouse' => 'Ship from',
        'warehouse_placeholder' => 'Choose a warehouse',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'no_warehouses' => 'There is nowhere to ship this from yet.',
        'no_warehouses_action' => 'Set up a warehouse',
    ],

    // The panel under the warehouse picker. One row per product, not per line: an order may
    // carry the same product twice, and what can be shipped depends on the two added together.
    'availability' => [
        'heading' => 'What this warehouse holds',
        'hint' => 'A guide, not a reservation — these figures move as colleagues record their own work, so the answer that counts is the one you get on confirming.',
        'item' => 'Product',
        'required' => 'Needed',
        'on_hand' => 'Available',
        'short' => 'Short',
        'empty' => 'Nothing on this order points at a product that still exists, so nothing will be taken out.',
    ],

    'dialog' => [
        'fulfil' => [
            'title' => 'Fulfil this order?',
            // Plural, because "All 1 lines" is what a single-line order reads as otherwise.
            'description' => '{1}One line is taken out of :warehouse and the order is closed. Stock moves as soon as you confirm, and this cannot be undone.|[2,*]All :count lines are taken out of :warehouse and the order is closed. Stock moves as soon as you confirm, and this cannot be undone.',
            'submit' => 'Fulfil order',
            'submitting' => 'Fulfilling…',
        ],
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
        'fulfilled' => 'Order fulfilled and stock updated.',
        'cancelled' => 'Sales order cancelled.',
        'deleted' => 'Sales order deleted.',
    ],

    'error' => [
        'not_pending' => 'This order has already been fulfilled or cancelled.',
        'fulfilled_locked' => 'A fulfilled order cannot be changed or deleted.',
        // Plural by count rather than by joining names, because a list separator and the word
        // order around it differ across the three locales. The panel lists them in rows.
        'short' => '{1}Not enough stock: this warehouse holds :available of :item and the order needs :required.|[2,*]:count products do not have enough stock in this warehouse. The panel below shows which.',
        // The lost race, which the lock makes unreachable — see the controller. Nothing was
        // written, so trying again is the whole of the advice.
        'short_raced' => 'Somebody moved this stock while the order was shipping. Nothing was taken out — check the figures and try again.',
    ],
];
