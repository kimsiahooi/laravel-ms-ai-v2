<?php

declare(strict_types=1);

/*
| Words shared by every order screen — purchase and sales alike. What lives here is
| what the lines editor says: a line, its discount, and what the order comes to.
| Anything belonging to one side of the trade (who it is with, what its statuses are
| called) belongs in that module's own file instead.
|
| The lines editor is deliberately spare with its wording. It is a grid somebody works
| across at speed, and a column header that needs reading twice costs more than the
| sentence it saved.
*/

return [
    // One row of the editor. `item` is the default heading for the first column; a
    // module with its own word for what it trades passes that instead.
    'line' => [
        'item' => 'Item',
        'item_placeholder' => 'Choose a product or material',
        'item_search' => 'Search by name or SKU…',
        'item_empty' => 'Nothing matches.',
        'quantity' => 'Quantity',
        'quantity_placeholder' => 'e.g. 12',
        'unit_price' => 'Unit price',
        'unit_price_placeholder' => 'e.g. 9.50',
        'discount' => 'Discount',
        // Never on screen: the accessible name of the box beside the discount type,
        // which the visible "Discount" label already names for a sighted reader.
        'discount_value' => 'Discount value',
        'taxable' => 'Taxable',
        // "Line total", not "Amount", so it cannot be misread as the discount's
        // fixed-amount option sitting two columns to its left.
        'amount' => 'Line total',
        'remove' => 'Remove line :number',
    ],

    // App\Enums\DiscountType. Short because they are read inside a narrow select in a
    // row of seven columns — the header above them already says "Discount".
    'discount_type' => [
        'none' => 'None',
        'percent' => 'Percent',
        'amount' => 'Amount',
    ],

    'lines' => [
        'add' => 'Add line',
        'empty' => 'No lines yet. Add one to say what is being ordered.',
    ],

    'totals' => [
        'subtotal' => 'Subtotal',
        'discount' => 'Discount',
        'tax' => 'Tax (:rate%)',
        'total' => 'Total',
        // Said out loud because the figures move as somebody types, and a number that
        // moves invites the question of which one is real.
        'estimate' => 'A running estimate. What gets stored is what the server works out from these lines when the order is saved.',
    ],
];
