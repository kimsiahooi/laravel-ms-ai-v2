<?php

declare(strict_types=1);

return [
    'title' => 'Pemindahan stok',
    'subtitle' => 'Stok yang berpindah dari satu gudang ke gudang lain. Seperti lejar, tiada apa-apa di sini boleh disunting — pemindahan yang tersalah arah dibetulkan dengan memindahkan semula.',

    'search_placeholder' => 'Cari item, gudang atau nota…',

    'filter' => [
        'warehouse' => 'Gudang',
        'all_warehouses' => 'Mana-mana gudang',
        'warehouses_selected' => 'Mana-mana daripada :count gudang',
        'warehouse_hint' => '[0,1] Sepadan dengan mana-mana hujung pemindahan — stok yang keluar atau yang masuk.|[2,*] Memaparkan pemindahan yang melibatkan mana-mana daripada :count gudang ini, di mana-mana hujung.',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
    ],

    'column' => [
        'item' => 'Item',
        'from' => 'Dari',
        'to' => 'Ke',
        'quantity' => 'Kuantiti',
        'moved' => 'Dipindahkan',
        'user' => 'Oleh',
        'notes' => 'Nota',
    ],

    'empty' => [
        'title' => 'Belum ada pemindahan',
        'description' => 'Pindahkan stok antara dua gudang dan ia akan muncul di sini.',
    ],

    'no_match' => [
        'title' => 'Tiada pemindahan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'no_setup' => [
        'title' => 'Sediakan gudang kedua dahulu',
        'description' => 'Pemindahan mengalihkan stok antara dua gudang, dan hanya ada satu setakat ini.',
        'action' => 'Pergi ke gudang',
    ],

    'no_items' => [
        'title' => 'Tambah sesuatu untuk dipindahkan',
        'description' => 'Stok dikira dalam produk dan bahan mentah, dan katalog masih kosong.',
        'action' => 'Pergi ke produk',
    ],

    'create' => [
        'trigger' => 'Pindahkan stok',
        'title' => 'Pindahkan stok',
        'description' => 'Apa yang berpindah, dari mana, dan ke mana.',
        'submit' => 'Rekod pemindahan',
        'submitting' => 'Merekod…',
    ],

    'field' => [
        'item' => 'Item',
        'item_placeholder' => 'Pilih produk atau bahan',
        'item_search' => 'Cari mengikut nama atau SKU…',
        'item_empty' => 'Tiada apa-apa sepadan.',
        'item_group_product' => 'Produk',
        'item_group_raw_material' => 'Bahan mentah',
        'from' => 'Dari gudang',
        'from_placeholder' => 'Di mana stok berada sekarang',
        'to' => 'Ke gudang',
        'to_placeholder' => 'Ke mana ia dihantar',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'quantity' => 'Kuantiti',
        'quantity_placeholder' => 'cth. 12',
        'notes' => 'Nota',
        'notes_placeholder' => 'Sebab, atau apa-apa yang perlu diingat',
    ],

    'error' => [
        'insufficient' => 'Hanya :available ada di sumber, dan ini akan memindahkan :requested.',
    ],

    'toast' => [
        'recorded' => 'Pemindahan direkodkan.',
    ],
];
