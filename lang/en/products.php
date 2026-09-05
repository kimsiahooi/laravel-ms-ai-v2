<?php

declare(strict_types=1);

/*
| Products — what the workspace sells. Chrome shared with every other list (search,
| paging, Cancel, Edit, Delete) lives in common.php; unit names live in units.php.
*/

return [
    'title' => 'Products',
    'subtitle' => 'What you sell, and how it is filed.',

    'search_placeholder' => 'Search name, SKU or barcode…',

    'filter' => [
        'material' => 'Built from',
        'all_materials' => 'Any material',
        'materials_selected' => ':count materials',
        'material_search' => 'Search materials…',
        'material_empty' => 'No materials match.',
        'unit' => 'Unit',
        'all_units' => 'All units',
    ],

    'column' => [
        'name' => 'Product',
        'sku' => 'SKU',
        'category' => 'Category',
        'supplier' => 'Supplier',
        'created' => 'Added',
        'creator' => 'Added by',
        'view_category' => 'View :name in categories',
        'view_supplier' => 'View :name in suppliers',
    ],

    'empty' => [
        'title' => 'No products yet',
        'description' => 'Add the first one and it will be ready to sell, count and build from your raw materials.',
    ],

    'no_match' => [
        'title' => 'No products match',
        'description' => 'Nothing here matches “:term”.',
    ],

    'create' => [
        'trigger' => 'New product',
        'title' => 'New product',
        'description' => 'A code to refer to it by, and the unit you sell it in.',
        'submit' => 'Create product',
        'submitting' => 'Creating…',
    ],

    'edit' => [
        'title' => 'Edit product',
        'description' => 'Changes apply everywhere this product is used.',
        'submit' => 'Save changes',
        'submitting' => 'Saving…',
    ],

    'group' => [
        'identity' => 'What it is',
        'filing' => 'How it is filed',
        'filing_hint' => 'Both optional — they group the product in lists and reports, and can wait until you have decided.',
    ],

    'field' => [
        'name' => 'Name',
        'name_placeholder' => 'e.g. Folding step stool',
        'sku' => 'SKU',
        'sku_placeholder' => 'e.g. P-001',
        'sku_hint' => 'Your own code for this product. It appears on sales orders and invoices, and no two products can share one.',
        'barcode' => 'Barcode',
        'barcode_placeholder' => 'Scan or type a barcode',
        'barcode_hint' => 'Scanned to find this product during counts, movements and transfers.',
        'unit' => 'Unit',
        'unit_placeholder' => 'Choose a unit',
        'unit_hint' => 'What you sell it in. Every quantity recorded against this product is a number of these.',
        'description' => 'Description',
        'description_placeholder' => 'What it is, in a line or two',
        'image' => 'Photo',
        'image_hint' => 'JPG, PNG or WebP, up to 2 MB. It appears beside the product in every list.',
        'image_remove' => 'Remove photo',
        'image_alt' => 'Product photo',
        'category' => 'Category',
        'category_placeholder' => 'Choose a category',
        'category_search' => 'Search categories…',
        'category_empty' => 'No categories match.',
        'supplier' => 'Supplier',
        'supplier_placeholder' => 'Choose a supplier',
        'supplier_search' => 'Search suppliers…',
        'supplier_empty' => 'No suppliers match.',
    ],

    'bom' => [
        'action' => 'Bill of materials',
        'title' => 'Bill of materials',
        'description' => 'The raw materials that go into :name, and how much of each it takes to make one.',
        'submit' => 'Save bill',
        'submitting' => 'Saving…',
        'add' => 'Add material',
        'line' => 'Material :number',
        'column_material' => 'Material',
        'column_quantity' => 'Quantity per unit',
        'material_placeholder' => 'Choose a material',
        'material_search' => 'Search materials…',
        'material_empty' => 'No materials match.',
        'quantity_placeholder' => 'e.g. 0.35',
        'remove' => 'Remove material :number',
        'empty' => 'No materials yet. Add the first one to describe what this product is made of.',
        'none_available' => 'There are no raw materials in this workspace yet. Add one first, and it will be available here.',
        'count' => '{0} No bill|{1} :count material|[2,*] :count materials',
    ],

    'confirm' => [
        'delete_title' => 'Delete :name?',
        'delete_description' => 'Orders already raised for this product keep their record of it — you simply will not be able to pick it for a new one.',
        'delete_submit' => 'Delete product',
        'delete_submitting' => 'Deleting…',
    ],

    'toast' => [
        'bom_saved' => 'Bill of materials saved for :name.',
        'created' => ':name created.',
        'updated' => ':name updated.',
        'deleted' => ':name deleted.',
    ],
];
