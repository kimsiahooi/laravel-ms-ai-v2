<?php

declare(strict_types=1);

/*
| Rujuk lang/en/stock-takes.php untuk nota tentang modul ini.
*/

return [
    'title' => 'Pengiraan stok',
    'subtitle' => 'Kira apa yang benar-benar ada dalam gudang, kemudian rekodkan perbezaannya.',
    'search_placeholder' => 'Cari gudang, tapak atau nota…',

    'column' => [
        'id' => 'Kiraan #',
        'warehouse' => 'Gudang',
        'status' => 'Status',
        'progress' => 'Dikira',
        'variances' => 'Perbezaan',
        'opened_by' => 'Dibuka oleh',
        'posted_by' => 'Direkod oleh',
        'posted_at' => 'Direkod',
        'created_at' => 'Dimulakan',
    ],

    'status' => [
        'draft' => 'Sedang dikira',
        'posted' => 'Direkod',
        'cancelled' => 'Dibatalkan',
    ],

    'action' => [
        'new' => 'Pengiraan stok baharu',
        'view' => 'Buka helaian kiraan',
        'post' => 'Rekod kiraan',
        'cancel' => 'Batalkan pengiraan',
        'delete' => 'Padam',
        'add_item' => 'Tambah item yang dijumpai di rak',
    ],

    'dialog' => [
        'create' => [
            'title' => 'Pengiraan stok baharu',
            'description' => 'Pilih gudang yang hendak dikira. Setiap item di dalamnya disenaraikan untuk anda, dan anda boleh tambah apa-apa lagi yang dijumpai.',
            'submit' => 'Mula mengira',
            'submitting' => 'Memulakan…',
        ],
        'post' => [
            'title' => 'Rekodkan kiraan ini?',
            'description' => 'Stok yang ada akan menjadi jumlah yang anda kira, dan perbezaannya ditulis ke dalam lejar. :counted daripada :total baris akan digunakan. Ini tidak boleh dibatalkan.',
            'submit' => 'Rekod kiraan',
            'submitting' => 'Merekod…',
        ],
        'cancel' => [
            'title' => 'Batalkan pengiraan stok ini?',
            'description' => 'Kiraan itu dibuang dan stok dibiarkan betul-betul seperti sedia ada. Pengiraan yang dibatalkan tidak boleh dibuka semula.',
            'submit' => 'Batalkan pengiraan',
            'submitting' => 'Membatalkan…',
        ],
        'delete' => [
            'title' => 'Padam pengiraan stok ini?',
            'description' => 'Helaian kiraan dibuang daripada senarai. Hanya pengiraan yang belum direkod boleh dipadam.',
            'submit' => 'Padam',
            'submitting' => 'Memadam…',
        ],
        'add_item' => [
            'title' => 'Tambah item ke dalam kiraan ini',
            'description' => 'Sesuatu di rak yang belum dibawa oleh gudang ini. Jangkaannya bermula pada sifar.',
            'submit' => 'Tambah ke kiraan',
            'submitting' => 'Menambah…',
        ],
    ],

    'field' => [
        'warehouse' => 'Gudang',
        'notes' => 'Nota',
        'notes_placeholder' => 'Sebab pengiraan ini dijalankan',
        'item' => 'Item',
        'item_placeholder' => 'Cari produk atau bahan mentah',
        'item_search' => 'Cari mengikut nama atau SKU',
        'item_empty' => 'Tiada apa-apa sepadan.',
        'warehouse_placeholder' => 'Pilih gudang',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'item_group_product' => 'Produk',
        'item_group_raw_material' => 'Bahan mentah',
    ],

    'sheet' => [
        'heading' => 'Helaian kiraan',
        'item' => 'Item',
        'expected' => 'Dijangka',
        'counted' => 'Dikira',
        'difference' => 'Perbezaan',
        'applied' => 'Digunakan',
        'not_counted' => 'Belum dikira',
        'saved' => 'Disimpan',
        'saving' => 'Menyimpan…',
        'empty' => 'Gudang ini belum menyimpan apa-apa. Tambah sahaja apa yang anda jumpa di rak.',
    ],

    'summary' => [
        'lines' => 'Item dalam helaian',
        'counted' => 'Dikira setakat ini',
        'variances' => 'Perbezaan dijumpai',
        'notes' => 'Nota',
        'opened_by' => 'Dibuka oleh',
        'posted_by' => 'Direkod oleh',
    ],

    'toast' => [
        'opened' => 'Pengiraan stok dimulakan.',
        'posted' => 'Kiraan direkodkan dan stok dikemas kini.',
        'cancelled' => 'Pengiraan stok dibatalkan.',
        'deleted' => 'Pengiraan stok dipadam.',
        'item_added' => 'Item ditambah ke dalam kiraan.',
    ],

    'error' => [
        'insufficient' => 'Hanya :available tersedia, dan ini akan menggerakkan :requested.',
        'not_draft' => 'Pengiraan stok ini telah direkod atau dibatalkan.',
        'duplicate_item' => 'Item itu sudah ada dalam helaian kiraan ini.',
        'posted_locked' => 'Pengiraan stok yang telah direkod tidak boleh dipadam.',
    ],

    // Dicap pada setiap baris lejar yang dihasilkan oleh kiraan, supaya sesuatu
    // pergerakan boleh dijejaki kembali kepada helaian yang menyebabkannya.
    'movement' => [
        'notes' => 'Pengiraan stok #:id',
    ],

    'empty' => [
        'title' => 'Belum ada pengiraan stok',
        'description' => 'Mulakan satu untuk mengira apa yang sebenarnya ada dalam sesebuah gudang.',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Mana-mana status',
    ],
];
