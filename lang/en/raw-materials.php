<?php

declare(strict_types=1);

/*
| Raw materials — what the workspace buys in and consumes. Chrome shared with every
| other list (search, paging, Cancel, Edit, Delete) lives in common.php.
*/

return [
    'title' => 'Raw materials',
    'subtitle' => 'What you buy in and consume to make what you sell.',

    'search_placeholder' => 'Search name, SKU or barcode…',

    'column' => [
        'name' => 'Material',
        'sku' => 'SKU',
        'unit' => 'Unit',
        'created' => 'Added',
        'creator' => 'Added by',
    ],

    'empty' => [
        'title' => 'No raw materials yet',
        'description' => 'Add the first one and it will be ready to receive, count and build products from.',
    ],

    'no_match' => [
        'title' => 'No materials match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New material',
        'title' => 'New raw material',
        'description' => 'A code to refer to it by, and the unit you count it in.',
        'submit' => 'Create material',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit raw material',
        'description' => 'Changes apply everywhere this material is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'field' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. Steel rod 12mm',
        'sku' => 'SKU',
        'sku_placeholder' => 'e.g. RM-001',
        'sku_hint' => 'Your own code for this material. It appears on purchase orders and stock lists, and no two materials can share one.',
        'barcode' => 'Barcode',
        'barcode_placeholder' => 'Scan or type a barcode',
        'barcode_hint' => 'Scanned to find this material during counts, movements and transfers.',
        'unit' => 'Unit',
        'unit_placeholder' => 'Choose a unit',
        'unit_hint' => 'What you count it in. Every quantity recorded against this material is a number of these, so pick the one you buy and issue it in.',
    ],

    'confirm' => [
        'blocked_title' => 'Cannot delete :name',
        'blocked_description' => '{1} It is used in the bill of materials for :products. Remove it from that bill first, then the material can be deleted.|[2,*] It is used in the bills of materials for :count products (:products). Remove it from those bills first, then the material can be deleted.',
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Stock already recorded against this material keeps its history — you simply will not be able to pick it for anything new.',
        'delete_submit' => 'Delete material',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'in_use' => '{1} :name cannot be deleted — it is used in the bill of materials for :products.|[2,*] :name cannot be deleted — it is used in the bills of materials for :count products (:products).',
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
