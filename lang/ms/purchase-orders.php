<?php

declare(strict_types=1);

/*
| Rujuk lang/en/purchase-orders.php untuk nota tentang modul ini.
*/

return [
    'title' => 'Pesanan belian',
    'subtitle' => 'Apa yang telah dipesan daripada pembekal anda, dan berapa kosnya. Menerima pesanan akan memasukkan barang ke dalam gudang.',
    'search_placeholder' => 'Cari nombor pesanan, pembekal atau nota…',

    'column' => [
        'number' => 'Pesanan',
        'supplier' => 'Pembekal',
        'status' => 'Status',
        'total' => 'Jumlah',
        'expected' => 'Dijangka',
        'created' => 'Dibuat',
    ],

    'status' => [
        'pending' => 'Belum tiba',
        'received' => 'Diterima',
        'cancelled' => 'Dibatalkan',
    ],

    'action' => [
        'new' => 'Pesanan belian baharu',
        'edit' => 'Sunting pesanan',
        'receive' => 'Terima',
        'cancel' => 'Batalkan pesanan',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Mana-mana status',
        'supplier' => 'Pembekal',
        'all_suppliers' => 'Mana-mana pembekal',
        'supplier_search' => 'Cari pembekal…',
        'supplier_empty' => 'Tiada pembekal sepadan.',
    ],

    'create' => [
        'title' => 'Pesanan belian baharu',
        'crumb' => 'Pesanan baharu',
        'subtitle' => 'Daripada siapa anda membeli, apa yang dibeli, dan harga yang dipersetujui. Nombornya diberikan apabila pesanan disimpan.',
        'submit' => 'Simpan pesanan',
        'submitting' => 'Menyimpan…',
    ],

    'edit' => [
        'title' => 'Sunting :number',
        'crumb' => 'Sunting',
    ],

    'lines' => [
        'heading' => 'Apa yang dipesan',
    ],

    'field' => [
        'supplier' => 'Pembekal',
        'supplier_placeholder' => 'Daripada siapa anda membeli',
        'supplier_search' => 'Cari pembekal…',
        'supplier_empty' => 'Tiada pembekal sepadan.',
        'currency' => 'Mata wang',
        'currency_placeholder' => 'Pilih mata wang',
        'exchange_rate' => 'Kadar tukaran',
        'exchange_rate_placeholder' => 'cth. 4.35',
        'exchange_rate_hint' => 'Berapa nilai mata wang anda sendiri bagi satu unit mata wang pesanan, pada hari pesanan dipersetujui.',
        'expected_date' => 'Jangkaan penghantaran',
        'expected_date_hint' => 'Hari barang sepatutnya tiba. Untuk perancangan sahaja — tiada apa-apa berlaku pada hari itu.',
        'notes' => 'Nota',
        'notes_placeholder' => 'Terma, rujukan sebut harga, atau apa-apa yang perlu diingat',
    ],

    'line' => [
        'item' => 'Item',
        'quantity' => 'Kuantiti',
        'unit_cost' => 'Kos seunit',
        'discount' => 'Diskaun',
        'total' => 'Jumlah baris',
    ],

    'summary' => [
        'supplier' => 'Pembekal',
        'currency' => 'Mata wang',
        'rate' => 'pada :rate',
        'expected' => 'Jangkaan penghantaran',
        'raised_by' => 'Dibuat oleh',
        'received_by' => 'Diterima oleh',
        'received_at' => 'Diterima',
        'received_into' => 'Diterima ke',
        'notes' => 'Nota',
    ],

    'receive' => [
        'heading' => 'Penerimaan',
        'description' => 'Merekod penerimaan akan menambah setiap baris ke satu gudang dan menutup pesanan. Pilih di mana barang itu benar-benar sampai.',
        'warehouse' => 'Terima ke gudang',
        'warehouse_placeholder' => 'Pilih gudang',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'no_warehouses' => 'Belum ada tempat untuk menerima barang ini.',
        'no_warehouses_action' => 'Sediakan gudang',
    ],

    'dialog' => [
        'receive' => [
            'title' => 'Terima pesanan ini?',
            'description' => 'Kesemua :lines baris akan ditambah ke :warehouse dan pesanan ditutup. Stok bergerak sebaik sahaja anda mengesahkan, dan ini tidak boleh dibatalkan.',
            'submit' => 'Terima pesanan',
            'submitting' => 'Menerima…',
        ],
        'cancel' => [
            'title' => 'Batalkan pesanan ini?',
            'description' => 'Pesanan ditutup dan tiada stok dipindahkan. Pesanan yang dibatalkan tidak boleh dibuka semula atau diterima kemudian.',
            'submit' => 'Batalkan pesanan',
            'submitting' => 'Membatalkan…',
        ],
    ],

    'empty' => [
        'title' => 'Belum ada pesanan belian',
        'description' => 'Buat satu untuk merekod apa yang anda pesan dan harga yang dipersetujui.',
    ],

    'no_match' => [
        'title' => 'Tiada pesanan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'no_setup' => [
        'title' => 'Tambah pembekal dahulu',
        'description' => 'Pesanan dibuat dengan seseorang, dan belum ada sesiapa untuk membuat pesanan dengannya.',
        'action' => 'Pergi ke pembekal',
    ],

    'toast' => [
        'created' => 'Pesanan belian dibuat.',
        'updated' => 'Pesanan belian dikemas kini.',
        'received' => 'Pesanan diterima dan stok dikemas kini.',
        'cancelled' => 'Pesanan belian dibatalkan.',
        'deleted' => 'Pesanan belian dipadam.',
    ],

    'error' => [
        'not_pending' => 'Pesanan ini sudah diterima atau dibatalkan.',
        'insufficient' => 'Hanya :available ada, dan ini akan memindahkan :requested.',
        'received_locked' => 'Pesanan yang telah diterima tidak boleh diubah atau dipadam.',
        'insufficient' => 'Hanya :available ada, dan penerimaan ini akan memindahkan :requested.',
    ],
];
