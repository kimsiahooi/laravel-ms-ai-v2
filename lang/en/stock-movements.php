<?php

declare(strict_types=1);

/*
| The stock ledger. One screen: a list, a dialog over it, and no confirmation — there
| is nothing to delete. Chrome shared with every other list lives in common.php.
*/

return [
    'title' => 'Stock movements',
    'subtitle' => 'Everything that has moved in or out, and why. Nothing here can be edited — a mistake is corrected by recording the opposite.',

    'search_placeholder' => 'Search item, warehouse or notes…',

    'filter' => [
        'warehouse' => 'Warehouse',
        'all_warehouses' => 'Any warehouse',
        'warehouses_selected' => 'Any of :count warehouses',
        'warehouse_hint' => '[0,1] Tick more than one to widen the search — a movement need only be in one of them.|[2,*] Showing movements in any of these :count warehouses, not movements somehow in all of them.',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'reason' => 'Reason',
        'all_reasons' => 'Any reason',
        'reasons_selected' => 'Any of :count reasons',
        'reason_hint' => '[0,1] Tick more than one to widen the search — a movement need only have one of them.|[2,*] Showing movements with any of these :count reasons, not movements somehow having all of them.',
    ],

    'column' => [
        'item' => 'Item',
        'warehouse' => 'Warehouse',
        'quantity' => 'Change',
        'reason' => 'Reason',
        'recorded' => 'Recorded',
        'user' => 'By',
        'notes' => 'Notes',
    ],

    'item_type' => [
        'product' => 'Product',
        'raw_material' => 'Raw material',
    ],

    'reason' => [
        'adjustment' => 'Adjustment',
        'stock_take' => 'Stock take',
        'transfer_in' => 'Transfer in',
        'transfer_out' => 'Transfer out',
        'purchase_receipt' => 'Purchase receipt',
        'purchase_return' => 'Purchase return',
        'sales_fulfillment' => 'Sale',
        'sales_return' => 'Sales return',
        'production_consume' => 'Production use',
        'production_output' => 'Production output',
    ],

    'empty' => [
        'title' => 'Nothing has moved yet',
        'description' => 'Record the first movement and it will appear here, along with everything a purchase, sale or transfer does later.',
    ],

    'no_match' => [
        'title' => 'No movements match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'no_setup' => [
        'title' => 'Set up a warehouse first',
        'description' => 'Stock moves through a warehouse, and there is nowhere to move it yet.',
        'action' => 'Go to warehouses',
    ],

    'no_items' => [
        'title' => 'Add something to move',
        'description' => 'Stock is counted in products and raw materials, and the catalogue is empty.',
        'action' => 'Go to products',
    ],

    'create' => [
        'trigger' => 'Record movement',
        'title' => 'Record a movement',
        'description' => 'What moved, where, and how much.',
        'submit' => 'Record movement',
        'submitting' => 'Recording…',
    ],

    'field' => [
        'warehouse' => 'Warehouse',
        'warehouse_placeholder' => 'Choose a warehouse',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'item' => 'Item',
        'item_placeholder' => 'Choose a product or material',
        'item_search' => 'Search by name or SKU…',
        'item_empty' => 'Nothing matches.',
        'item_group_product' => 'Products',
        'item_group_raw_material' => 'Raw materials',
        'type' => 'What happened',
        'type_in' => 'Stock came in',
        'type_out' => 'Stock went out',
        'type_set' => 'Set the level',
        'type_hint_in' => 'Adds to what is already there.',
        'type_hint_out' => 'Takes away from what is there. Refused if there is not enough.',
        'type_hint_set' => 'Replaces the number entirely, whatever it is now — for after a count.',
        'quantity' => 'Quantity',
        'quantity_placeholder' => 'e.g. 12',
        'quantity_placeholder_set' => 'e.g. 12 — the new total',
        'notes' => 'Notes',
        'notes_placeholder' => 'Why, or anything worth remembering',
    ],

    'error' => [
        'insufficient' => 'Only :available available, and this would take :requested.',
    ],

    'toast' => [
        'recorded' => 'Movement recorded.',
    ],
];
