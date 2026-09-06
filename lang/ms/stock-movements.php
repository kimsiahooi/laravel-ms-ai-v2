<?php

declare(strict_types=1);

/*
| Rujuk lang/en/stock-movements.php untuk nota tentang modul ini.
*/

return [
    'title' => 'Pergerakan stok',
    'subtitle' => 'Segala yang masuk atau keluar, dan sebabnya. Tiada apa-apa di sini boleh diedit — kesilapan dibetulkan dengan merekod pergerakan bertentangan.',

    'search_placeholder' => 'Cari item, gudang atau nota…',

    'filter' => [
        'warehouse' => 'Gudang',
        'all_warehouses' => 'Mana-mana gudang',
        'warehouses_selected' => 'Mana-mana daripada :count gudang',
        'warehouse_hint' => '[0,1] Tandakan lebih daripada satu untuk meluaskan carian — pergerakan hanya perlu berada di salah satu daripadanya.|[2,*] Menunjukkan pergerakan di mana-mana daripada :count gudang ini, bukan pergerakan yang entah bagaimana berada di kesemuanya.',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'reason' => 'Sebab',
        'all_reasons' => 'Mana-mana sebab',
        'reasons_selected' => 'Mana-mana daripada :count sebab',
        'reason_hint' => '[0,1] Tandakan lebih daripada satu untuk meluaskan carian — pergerakan cuma perlu ada salah satu daripadanya.|[2,*] Memaparkan pergerakan dengan mana-mana daripada :count sebab ini, bukan yang mempunyai kesemuanya.',
    ],

    'column' => [
        'item' => 'Item',
        'warehouse' => 'Gudang',
        'quantity' => 'Perubahan',
        'reason' => 'Sebab',
        'recorded' => 'Direkod',
        'user' => 'Oleh',
        'notes' => 'Nota',
        'source' => 'Sumber',
    ],

    'item_type' => [
        'product' => 'Produk',
        'raw_material' => 'Bahan mentah',
    ],

    'reason' => [
        'adjustment' => 'Pelarasan',
        'stock_take' => 'Pengiraan stok',
        'transfer_in' => 'Pemindahan masuk',
        'transfer_out' => 'Pemindahan keluar',
        'purchase_receipt' => 'Terimaan belian',
        'purchase_return' => 'Pulangan belian',
        'sales_fulfillment' => 'Jualan',
        'sales_return' => 'Pulangan jualan',
        'production_consume' => 'Guna pengeluaran',
        'production_output' => 'Hasil pengeluaran',
    ],

    'empty' => [
        'title' => 'Belum ada pergerakan',
        'description' => 'Rekod pergerakan pertama dan ia akan muncul di sini, bersama segala yang dilakukan oleh belian, jualan atau pemindahan kemudian.',
    ],

    'no_match' => [
        'title' => 'Tiada pergerakan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'no_setup' => [
        'title' => 'Sediakan gudang dahulu',
        'description' => 'Stok bergerak melalui gudang, dan belum ada tempat untuk memindahkannya.',
        'action' => 'Pergi ke gudang',
    ],

    'no_items' => [
        'title' => 'Tambah sesuatu untuk digerakkan',
        'description' => 'Stok dikira dalam produk dan bahan mentah, dan katalog masih kosong.',
        'action' => 'Pergi ke produk',
    ],

    'create' => [
        'trigger' => 'Rekod pergerakan',
        'title' => 'Rekod pergerakan',
        'description' => 'Apa yang bergerak, ke mana, dan berapa banyak.',
        'submit' => 'Rekod pergerakan',
        'submitting' => 'Merekod…',
    ],

    'field' => [
        'warehouse' => 'Gudang',
        'warehouse_placeholder' => 'Pilih gudang',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'item' => 'Item',
        'item_placeholder' => 'Pilih produk atau bahan',
        'item_search' => 'Cari mengikut nama atau SKU…',
        'item_empty' => 'Tiada apa-apa sepadan.',
        'item_group_product' => 'Produk',
        'item_group_raw_material' => 'Bahan mentah',
        'type' => 'Apa yang berlaku',
        'type_in' => 'Stok masuk',
        'type_out' => 'Stok keluar',
        'type_set' => 'Tetapkan paras',
        'type_hint_in' => 'Menambah kepada yang sedia ada.',
        'type_hint_out' => 'Menolak daripada yang sedia ada. Ditolak jika tidak mencukupi.',
        'type_hint_set' => 'Menggantikan nombor itu sepenuhnya, apa pun nilainya sekarang — untuk selepas pengiraan.',
        'quantity' => 'Kuantiti',
        'quantity_placeholder' => 'cth. 12',
        'quantity_placeholder_set' => 'cth. 12 — jumlah baharu',
        'notes' => 'Nota',
        'notes_placeholder' => 'Sebabnya, atau apa-apa yang perlu diingat',
    ],

    'error' => [
        'insufficient' => 'Hanya :available tersedia, dan ini memerlukan :requested.',
    ],

    'toast' => [
        'recorded' => 'Pergerakan direkodkan.',
    ],

    // Built at render time from `source_type` and `source_id` so the words are
    // the reader's, not the poster's — see the ledger's source cell.
    'source' => [
        'stock_take' => 'Pengiraan stok #:id',
        'stock_transfer' => 'Pemindahan #:id',
        'purchase_order' => 'Pesanan belian #:id',
    ],
];
