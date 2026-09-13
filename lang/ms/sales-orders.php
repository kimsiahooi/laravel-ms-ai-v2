<?php

declare(strict_types=1);

/*
| Pesanan jualan — lihat lang/en/sales-orders.php untuk nota penuh.
*/

return [
    'title' => 'Pesanan jualan',
    'subtitle' => 'Apa yang dipesan oleh pelanggan anda, dan jumlahnya. Memenuhi satu pesanan mengeluarkan barang dari gudang.',
    'search_placeholder' => 'Cari nombor pesanan, pelanggan atau nota…',

    'column' => [
        'number' => 'Pesanan',
        'customer' => 'Pelanggan',
        'status' => 'Status',
        'total' => 'Jumlah',
        'expected' => 'Dijanjikan',
        'created' => 'Diambil',
    ],

    'status' => [
        'pending' => 'Menunggu',
        'fulfilled' => 'Dipenuhi',
        'cancelled' => 'Dibatalkan',
    ],

    'action' => [
        'new' => 'Pesanan jualan baharu',
        'edit' => 'Edit pesanan',
        'fulfil' => 'Penuhi',
        'cancel' => 'Batalkan pesanan',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Mana-mana status',
        'customer' => 'Pelanggan',
        'all_customers' => 'Mana-mana pelanggan',
        'customer_search' => 'Cari pelanggan…',
        'customer_empty' => 'Tiada pelanggan sepadan.',
    ],

    'create' => [
        'title' => 'Pesanan jualan baharu',
        'crumb' => 'Pesanan baharu',
        'subtitle' => 'Kepada siapa anda menjual, apa yang mereka beli, dan harga yang dipersetujui. Nombor diberikan apabila pesanan disimpan.',
        'submit' => 'Simpan pesanan',
        'submitting' => 'Menyimpan…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
    ],

    'lines' => [
        'heading' => 'Apa yang dijual',
    ],

    'field' => [
        'customer' => 'Pelanggan',
        'customer_placeholder' => 'Kepada siapa anda menjual',
        'customer_search' => 'Cari pelanggan…',
        'customer_empty' => 'Tiada pelanggan sepadan.',
        'currency' => 'Mata wang',
        'currency_placeholder' => 'Pilih mata wang',
        'exchange_rate' => 'Kadar tukaran',
        'exchange_rate_placeholder' => 'cth. 4.35',
        'exchange_rate_hint' => 'Nilai satu unit mata wang pesanan dalam mata wang anda sendiri, pada hari pesanan dipersetujui.',
        'expected_date' => 'Penghantaran dijanjikan',
        'expected_date_hint' => 'Hari pelanggan menjangkakan barang, dan waktunya jika dipersetujui. Untuk perancangan sahaja — tiada apa berlaku padanya.',
        'notes' => 'Nota',
        'notes_placeholder' => 'Terma, rujukan pesanan belian, apa-apa yang perlu diingat',
    ],

    'line' => [
        'item' => 'Item',
        'item_placeholder' => 'Pilih produk',
        'quantity' => 'Kuantiti',
        'unit_price' => 'Harga seunit',
        'discount' => 'Diskaun',
        'total' => 'Jumlah baris',
    ],

    'summary' => [
        'customer' => 'Pelanggan',
        'currency' => 'Mata wang',
        'rate' => 'pada :rate',
        'expected' => 'Penghantaran dijanjikan',
        'raised_by' => 'Diambil oleh',
        'fulfilled_by' => 'Dihantar oleh',
        'fulfilled_at' => 'Dihantar',
        'fulfilled_from' => 'Dihantar dari',
        'notes' => 'Nota',
    ],

    'fulfil' => [
        'heading' => 'Pemenuhan',
        'description' => 'Menghantar pesanan mengeluarkan setiap baris dari satu gudang dan menutupnya. Pilih dari mana barang itu sebenarnya keluar.',
        'warehouse' => 'Hantar dari',
        'warehouse_placeholder' => 'Pilih gudang',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'no_warehouses' => 'Belum ada tempat untuk menghantar pesanan ini.',
        'no_warehouses_action' => 'Sediakan gudang',
    ],

    'availability' => [
        'heading' => 'Apa yang ada di gudang ini',
        'hint' => 'Panduan sahaja, bukan tempahan — angka ini berubah apabila rakan sekerja merekod kerja mereka sendiri, jadi jawapan yang muktamad ialah yang anda dapat semasa mengesahkan.',
        'item' => 'Produk',
        'required' => 'Diperlukan',
        'on_hand' => 'Ada',
        'short' => 'Tidak cukup',
        'empty' => 'Tiada apa-apa dalam pesanan ini merujuk kepada produk yang masih wujud, jadi tiada apa akan dikeluarkan.',
    ],

    'dialog' => [
        'fulfil' => [
            'title' => 'Penuhi pesanan ini?',
            'description' => '{1}Satu baris dikeluarkan dari :warehouse dan pesanan ditutup. Stok bergerak sebaik anda mengesahkan, dan ini tidak boleh dibatalkan.|[2,*]Kesemua :count baris dikeluarkan dari :warehouse dan pesanan ditutup. Stok bergerak sebaik anda mengesahkan, dan ini tidak boleh dibatalkan.',
            'submit' => 'Penuhi pesanan',
            'submitting' => 'Memenuhi…',
        ],
        'cancel' => [
            'title' => 'Batalkan pesanan ini?',
            'description' => 'Pesanan ditutup dan tiada stok dipindahkan. Pesanan yang dibatalkan tidak boleh dibuka semula atau dihantar kemudian.',
            'submit' => 'Batalkan pesanan',
            'submitting' => 'Membatalkan…',
        ],
    ],

    'empty' => [
        'title' => 'Belum ada pesanan jualan',
        'description' => 'Ambil satu untuk merekod apa yang dipesan pelanggan dan harga yang dipersetujui.',
    ],

    'no_match' => [
        'title' => 'Tiada pesanan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'no_setup' => [
        'title' => 'Tambah pelanggan dahulu',
        'description' => 'Pesanan diambil daripada seseorang, dan belum ada sesiapa untuk mengambilnya.',
        'action' => 'Pergi ke pelanggan',
    ],

    'toast' => [
        'created' => 'Pesanan jualan diambil.',
        'updated' => 'Pesanan jualan dikemas kini.',
        'fulfilled' => 'Pesanan dipenuhi dan stok dikemas kini.',
        'cancelled' => 'Pesanan jualan dibatalkan.',
        'deleted' => 'Pesanan jualan dipadam.',
    ],

    'error' => [
        'not_pending' => 'Pesanan ini sudah dipenuhi atau dibatalkan.',
        'fulfilled_locked' => 'Pesanan yang telah dipenuhi tidak boleh diubah atau dipadam.',
        'short' => '{1}Stok tidak mencukupi: gudang ini ada :available :item sedangkan pesanan memerlukan :required.|[2,*]:count produk tidak mempunyai stok yang mencukupi di gudang ini. Panel di bawah menunjukkan yang mana satu.',
        'short_raced' => 'Seseorang memindahkan stok ini semasa pesanan sedang dihantar. Tiada apa dikeluarkan — semak angkanya dan cuba lagi.',
    ],
];
