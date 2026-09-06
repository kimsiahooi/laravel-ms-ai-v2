<?php

declare(strict_types=1);

return [
    'title' => 'Stock takes',
    'subtitle' => 'Count what is physically in a warehouse, then post the difference.',
    'search_placeholder' => 'Search warehouse, site or notes…',

    'column' => [
        'id' => 'Count #',
        'warehouse' => 'Warehouse',
        'status' => 'Status',
        'progress' => 'Counted',
        'variances' => 'Differences',
        'opened_by' => 'Opened by',
        'posted_by' => 'Posted by',
        'posted_at' => 'Posted',
        'created_at' => 'Started',
    ],

    'status' => [
        'draft' => 'In progress',
        'posted' => 'Posted',
        'cancelled' => 'Cancelled',
    ],

    'action' => [
        'new' => 'New stock take',
        'view' => 'Open count sheet',
        'post' => 'Post count',
        'cancel' => 'Cancel take',
        'delete' => 'Delete',
        'add_item' => 'Add an item found on the shelf',
    ],

    'dialog' => [
        'create' => [
            'title' => 'New stock take',
            'description' => 'Pick the warehouse to count. Every item it holds is listed for you, and you can add anything else you find.',
            'submit' => 'Start counting',
            'submitting' => 'Starting…',
        ],
        'post' => [
            'title' => 'Post this count?',
            'description' => 'On-hand becomes what you counted, and the difference is written to the ledger. :counted of :total lines will be applied. This cannot be undone.',
            'submit' => 'Post count',
            'submitting' => 'Posting…',
        ],
        'cancel' => [
            'title' => 'Cancel this stock take?',
            'description' => 'The count is discarded and stock is left exactly as it is. You cannot reopen a cancelled take.',
            'submit' => 'Cancel take',
            'submitting' => 'Cancelling…',
        ],
        'delete' => [
            'title' => 'Delete this stock take?',
            'description' => 'The count sheet is removed from the list. Only a take that has not been posted can be deleted.',
            'submit' => 'Delete',
            'submitting' => 'Deleting…',
        ],
        'add_item' => [
            'title' => 'Add an item to this count',
            'description' => 'Something on the shelf that this warehouse does not carry yet. It starts at zero expected.',
            'submit' => 'Add to count',
            'submitting' => 'Adding…',
        ],
    ],

    'field' => [
        'warehouse' => 'Warehouse',
        'notes' => 'Notes',
        'notes_placeholder' => 'Why this count is being taken',
        'item' => 'Item',
        'item_placeholder' => 'Search a product or raw material',
        'item_search' => 'Search by name or SKU',
        'item_empty' => 'Nothing matches that.',
        'warehouse_placeholder' => 'Choose a warehouse',
        'warehouse_search' => 'Search warehouses…',
        'warehouse_empty' => 'No warehouses match.',
        'item_group_product' => 'Products',
        'item_group_raw_material' => 'Raw materials',
    ],

    'sheet' => [
        'heading' => 'Count sheet',
        'item' => 'Item',
        'expected' => 'Expected',
        'counted' => 'Counted',
        'difference' => 'Difference',
        'applied' => 'Applied',
        'not_counted' => 'Not counted',
        'saved' => 'Saved',
        'saving' => 'Saving…',
        'empty' => 'This warehouse holds nothing yet. Add whatever you find on the shelf.',
    ],

    'summary' => [
        'lines' => 'Items on the sheet',
        'counted' => 'Counted so far',
        'variances' => 'Differences found',
        'notes' => 'Notes',
        'opened_by' => 'Opened by',
        'posted_by' => 'Posted by',
    ],

    'toast' => [
        'opened' => 'Stock take started.',
        'posted' => 'Count posted and stock updated.',
        'cancelled' => 'Stock take cancelled.',
        'deleted' => 'Stock take deleted.',
        'item_added' => 'Item added to the count.',
    ],

    'error' => [
        'insufficient' => 'Only :available available, and this would move :requested.',
        'not_draft' => 'This stock take has already been posted or cancelled.',
        'duplicate_item' => 'That item is already on this count sheet.',
        'posted_locked' => 'A posted stock take cannot be deleted.',
    ],

    'empty' => [
        'title' => 'No stock takes yet',
        'description' => 'Start one to count what a warehouse is actually holding.',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Any status',
    ],
];
