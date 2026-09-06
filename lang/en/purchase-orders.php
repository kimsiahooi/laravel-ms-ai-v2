<?php

declare(strict_types=1);

/*
| Purchase orders — what the workspace has ordered from a supplier, and what happened
| when it arrived.
|
| Words shared with sales orders live in `orders.php`: a line, its discount, and what the
| document comes to. What is here is the half that belongs to buying — the counterparty is
| a supplier, the money on a line is a cost rather than a price, and the ending is a
| delivery being booked into a warehouse.
|
| The three statuses are the whole vocabulary of the module, so they are worth being
| precise about. "Pending" is an order that has been raised and not yet arrived — the only
| state anything can still be done to. "Received" and "Cancelled" are both terminal, and
| the screens say so rather than leaving somebody to discover it by pressing a button.
*/

return [
    'title' => 'Purchase orders',
    'subtitle' => 'What has been ordered from your suppliers, and what it will cost. Receiving one books the goods into a warehouse.',
    'search_placeholder' => 'Search order number, supplier or notes…',

    'column' => [
        'number' => 'Order',
        'supplier' => 'Supplier',
        'status' => 'Status',
        'total' => 'Total',
        'expected' => 'Expected',
        'created' => 'Raised',
    ],

    // App\Enums\PurchaseOrderStatus.
    'status' => [
        'pending' => 'Pending',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'action' => [
        'new' => 'New purchase order',
        'edit' => 'Edit order',
        'receive' => 'Receive',
        'cancel' => 'Cancel order',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Any status',
        'supplier' => 'Supplier',
        'all_suppliers' => 'Any supplier',
        'supplier_search' => 'Search suppliers…',
        'supplier_empty' => 'No suppliers match.',
    ],

    'create' => [
        'title' => 'New purchase order',
        'crumb' => 'New order',
        'subtitle' => 'Who you are buying from, what you are buying, and what you agreed to pay. The number is assigned when the order is saved.',
        'submit' => 'Save order',
        'submitting' => 'Saving…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
    ],

    'lines' => [
        'heading' => 'What is being ordered',
    ],

    'field' => [
        'supplier' => 'Supplier',
        'supplier_placeholder' => 'Who you are buying from',
        'supplier_search' => 'Search suppliers…',
        'supplier_empty' => 'No suppliers match.',
        'currency' => 'Currency',
        'currency_placeholder' => 'Choose a currency',
        'exchange_rate' => 'Exchange rate',
        'exchange_rate_placeholder' => 'e.g. 4.35',
        'exchange_rate_hint' => 'How much of your own currency one unit of the order currency is worth, on the day the order was agreed.',
        'expected_date' => 'Expected delivery',
        'expected_date_hint' => 'The day the goods are due. Used for planning only — nothing happens on it.',
        'notes' => 'Notes',
        'notes_placeholder' => 'Terms, a quote reference, anything worth remembering',
    ],

    // The read-only document. "Unit cost", not the shared editor's "Unit price": this
    // side of the trade is money going out.
    'line' => [
        'item' => 'Item',
        'quantity' => 'Quantity',
        'unit_cost' => 'Unit cost',
        'discount' => 'Discount',
        'total' => 'Line total',
    ],

    'summary' => [
        'supplier' => 'Supplier',
        'currency' => 'Currency',
        'rate' => 'at :rate',
        'expected' => 'Expected delivery',
        'raised_by' => 'Raised by',
        'received_by' => 'Received by',
        'received_at' => 'Received',
        'received_into' => 'Received into',
        'notes' => 'Notes',
    ],

    'receive' => [
        'heading' => 'Receiving',
        'description' => 'Booking the delivery in adds every line to one warehouse and closes the order. Choose where the goods actually landed.',
        'warehouse' => 'Receive into',
        'warehouse_placeholder' => 'Choose a warehouse',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'no_warehouses' => 'There is nowhere to receive this into yet.',
        'no_warehouses_action' => 'Set up a warehouse',
    ],

    'dialog' => [
        'receive' => [
            'title' => 'Receive this order?',
            'description' => 'All :lines lines are added to :warehouse and the order is closed. Stock moves as soon as you confirm, and this cannot be undone.',
            'submit' => 'Receive order',
            'submitting' => 'Receiving…',
        ],
        'cancel' => [
            'title' => 'Cancel this order?',
            'description' => 'The order is closed and no stock is moved. You cannot reopen a cancelled order, or receive against it later.',
            'submit' => 'Cancel order',
            'submitting' => 'Cancelling…',
        ],
    ],

    'empty' => [
        'title' => 'No purchase orders yet',
        'description' => 'Raise one to record what you have ordered and what you agreed to pay for it.',
    ],

    'no_match' => [
        'title' => 'No orders match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'no_setup' => [
        'title' => 'Add a supplier first',
        'description' => 'An order is raised with somebody, and there is nobody to raise one with yet.',
        'action' => 'Go to suppliers',
    ],

    'toast' => [
        'created' => 'Purchase order raised.',
        'updated' => 'Purchase order updated.',
        'received' => 'Order received and stock updated.',
        'cancelled' => 'Purchase order cancelled.',
        'deleted' => 'Purchase order deleted.',
    ],

    'error' => [
        'not_pending' => 'This order has already been received or cancelled.',
        // Unreachable for a receipt, which only ever adds — but StockService declares
        // the failure and the screen has to be able to say it.
        'insufficient' => 'Only :available available, and this would move :requested.',
        'received_locked' => 'A received order cannot be changed or deleted.',
        // Unreachable while a receipt only ever adds stock — see the controller — but
        // the service declares the refusal, so the words exist for the day it can.
        'insufficient' => 'Only :available available, and this receipt would move :requested.',
    ],
];
