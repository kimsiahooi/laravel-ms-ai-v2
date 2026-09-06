<?php

declare(strict_types=1);

/*
| Stock moving between warehouses. One screen: a list, a dialog over it, and no
| confirmation — there is nothing to delete. Chrome shared with every other list lives
| in common.php.
*/

return [
    'title' => 'Stock transfers',
    'subtitle' => 'Stock moving from one warehouse to another. Like the ledger, nothing here can be edited — a transfer sent the wrong way is corrected by transferring back.',

    'search_placeholder' => 'Search item, warehouse or notes…',

    'filter' => [
        'warehouse' => 'Warehouse',
        'all_warehouses' => 'Any warehouse',
        'warehouses_selected' => 'Any of :count warehouses',
        'warehouse_hint' => '[0,1] Matches either end of the transfer — stock leaving it or arriving at it.|[2,*] Showing transfers touching any of these :count warehouses, at either end.',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
    ],

    'column' => [
        'item' => 'Item',
        'from' => 'From',
        'to' => 'To',
        'quantity' => 'Quantity',
        'moved' => 'Moved',
        'user' => 'By',
        'notes' => 'Notes',
    ],

    'empty' => [
        'title' => 'Nothing has been transferred yet',
        'description' => 'Move stock between two warehouses and it will appear here.',
    ],

    'no_match' => [
        'title' => 'No transfers match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'no_setup' => [
        'title' => 'Set up a second warehouse first',
        'description' => 'A transfer moves stock between two warehouses, and there is only one to move it between.',
        'action' => 'Go to warehouses',
    ],

    'no_items' => [
        'title' => 'Add something to move',
        'description' => 'Stock is counted in products and raw materials, and the catalogue is empty.',
        'action' => 'Go to products',
    ],

    'create' => [
        'trigger' => 'Transfer stock',
        'title' => 'Transfer stock',
        'description' => 'What is moving, where from, and where to.',
        'submit' => 'Record transfer',
        'submitting' => 'Recording…',
    ],

    'field' => [
        'item' => 'Item',
        'item_placeholder' => 'Choose a product or material',
        'item_search' => 'Search by name or SKU…',
        'item_empty' => 'Nothing matches.',
        'item_group_product' => 'Products',
        'item_group_raw_material' => 'Raw materials',
        'from' => 'From warehouse',
        'from_placeholder' => 'Where the stock is now',
        'to' => 'To warehouse',
        'to_placeholder' => 'Where it is going',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'quantity' => 'Quantity',
        'quantity_placeholder' => 'e.g. 12',
        'notes' => 'Notes',
        'notes_placeholder' => 'Why, or anything worth remembering',
    ],

    'error' => [
        'insufficient' => 'Only :available available at the source, and this would move :requested.',
    ],

    'toast' => [
        'recorded' => 'Transfer recorded.',
    ],
];
