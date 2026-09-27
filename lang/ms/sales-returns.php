<?php

declare(strict_types=1);

return [
    'title' => 'Pemulangan jualan',
    'subtitle' => 'Barang yang dipulangkan pelanggan, dikreditkan pada harga yang dicaj kepada mereka.',
    'search_placeholder' => 'Cari nombor pemulangan atau pesanan, pelanggan atau nota…',

    'column' => [
        'number' => 'Pemulangan',
        'order' => 'Terhadap',
        'customer' => 'Pelanggan',
        'status' => 'Status',
        'reason' => 'Sebab',
        'total' => 'Kredit',
        'created' => 'Dibuka',
    ],

    'action' => [
        'new' => 'Pemulangan jualan baharu',
        'edit' => 'Edit pemulangan',
        'complete' => 'Selesaikan pemulangan',
        'cancel' => 'Batalkan pemulangan',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Sebarang status',
        'reason' => 'Sebab',
        'all_reasons' => 'Sebarang sebab',
    ],

    'create' => [
        'title' => 'Pemulangan terhadap :number',
        'crumb' => 'Pemulangan baharu',
        'subtitle' => 'Nyatakan berapa banyak setiap baris yang dihantar telah dipulangkan. Harga diambil daripada pesanan, jadi kredit sepadan dengan apa yang dicaj kepada pelanggan.',
        'submit' => 'Simpan pemulangan',
        'submitting' => 'Menyimpan…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
        'subtitle' => 'Hanya pemulangan yang belum selesai boleh diubah.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'order' => [
        'heading' => 'Mengkreditkan',
        'number' => 'Pesanan jualan',
        'customer' => 'Pelanggan',
        'fulfilled' => 'Dihantar',
        'locked' => 'Satu pemulangan mengkreditkan satu penghantaran, jadi ini tidak boleh diubah. Buka pemulangan baharu untuk mengkreditkan pesanan lain.',
    ],

    'field' => [
        'reason' => 'Sebab',
        'reason_placeholder' => 'Kenapa barang dipulangkan',
        'notes' => 'Nota',
        'notes_placeholder' => 'Rujukan, apa kata pelanggan, apa-apa yang perlu diingat',
    ],

    'lines' => [
        'heading' => 'Apa yang dipulangkan',
        'description' => 'Setiap baris penghantaran yang masih ada baki boleh dipulangkan. Biarkan kuantiti kosong untuk mengecualikan baris itu.',
        'fill_all' => 'Pulangkan semua',
        'empty' => 'Semua yang ada pada pesanan ini telah pun dipulangkan.',
    ],

    'line' => [
        'item' => 'Produk',
        'sold' => 'Dihantar',
        'returned' => 'Dipulangkan',
        'remaining' => 'Baki',
        'quantity' => 'Dipulangkan kini',
        'quantity_placeholder' => 'cth. 2',
        'unit_price' => 'Harga seunit',
        'discount' => 'Diskaun',
        'total' => 'Kredit baris',
    ],

    'complete' => [
        'heading' => 'Menerima semula barang',
        'description' => 'Menyelesaikan pemulangan memasukkan semula setiap baris ke dalam satu gudang dan menutupnya. Pilih ke mana barang itu sebenarnya pergi.',
        'warehouse' => 'Terima ke',
        'warehouse_placeholder' => 'Pilih gudang',
        'warehouse_search' => 'Cari gudang…',
        'warehouse_empty' => 'Tiada gudang sepadan.',
        'no_warehouses' => 'Belum ada tempat untuk menerimanya.',
        'no_warehouses_action' => 'Sediakan gudang',
    ],

    'summary' => [
        'order' => 'Pesanan jualan',
        'customer' => 'Pelanggan',
        'currency' => 'Mata wang',
        'rate' => 'pada :rate',
        'reason' => 'Sebab',
        'raised_by' => 'Dibuka oleh',
        'completed_by' => 'Diselesaikan oleh',
        'completed_at' => 'Selesai',
        'warehouse' => 'Diterima ke',
        'notes' => 'Nota',
    ],

    'dialog' => [
        'complete' => [
            'title' => 'Selesaikan pemulangan ini?',
            'description' => '{1}Satu baris dimasukkan semula ke :warehouse dan pemulangan ditutup. Stok bergerak sebaik sahaja anda mengesahkan, dan ini tidak boleh dibatalkan.|[2,*]Kesemua :count baris dimasukkan semula ke :warehouse dan pemulangan ditutup. Stok bergerak sebaik sahaja anda mengesahkan, dan ini tidak boleh dibatalkan.',
            'submit' => 'Selesaikan pemulangan',
            'submitting' => 'Menyelesaikan…',
        ],
        'cancel' => [
            'title' => 'Batalkan pemulangan ini?',
            'description' => 'Pemulangan ditutup dan tiada stok bergerak. Kuantiti padanya boleh dipulangkan semula. Anda tidak boleh membuka semula pemulangan yang dibatalkan.',
            'submit' => 'Batalkan pemulangan',
            'submitting' => 'Membatalkan…',
        ],
        'delete' => [
            'title' => 'Padam :number?',
            'description' => 'Pemulangan dibuang dan kuantiti padanya boleh dipulangkan semula. Tiada apa yang telah bergerak, jadi tiada apa yang diterbalikkan.',
            'submit' => 'Padam pemulangan',
            'submitting' => 'Memadam…',
        ],
    ],

    'empty' => [
        'title' => 'Belum ada pemulangan jualan',
        'description' => 'Pemulangan dibuka terhadap satu penghantaran. Buka pesanan jualan yang menghantar barang itu dan mulakan dari sana.',
        'action' => 'Pergi ke pesanan yang dihantar',
    ],

    'no_setup' => [
        'title' => 'Belum ada apa-apa yang dihantar',
        'description' => 'Barang hanya boleh dipulangkan setelah ia dihantar. Penuhi satu pesanan jualan dan ia menjadi boleh dipulangkan.',
        'action' => 'Pergi ke pesanan jualan',
    ],

    'no_match' => [
        'title' => 'Tiada pemulangan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'toast' => [
        'created' => 'Pemulangan jualan dibuka.',
        'updated' => 'Pemulangan jualan dikemas kini.',
        'deleted' => 'Pemulangan jualan dipadam.',
        'completed' => 'Pemulangan jualan selesai. Stok telah bergerak.',
        'cancelled' => 'Pemulangan jualan dibatalkan.',
    ],

    'error' => [
        'not_pending' => 'Pemulangan ini telah pun diselesaikan atau dibatalkan.',
        'completed_locked' => 'Pemulangan yang telah selesai tidak boleh diubah atau dipadam.',
        'no_order' => 'Pesanan jualan itu tidak boleh dipulangkan — ia mungkin telah dipadam, atau ia tidak pernah dihantar.',
        'nothing_returnable' => 'Semua yang ada pada pesanan itu telah pun dipulangkan.',
        'short_raced' => 'Seseorang menggerakkan stok ini semasa pemulangan sedang diselesaikan. Tiada apa dimasukkan semula — semak angkanya dan cuba lagi.',
        'over_return_now' => '{1}Satu lagi pemulangan telah diselesaikan sejak yang ini dibuka. Hanya :remaining :item boleh dipulangkan lagi, sedangkan pemulangan ini membawa :requested. Editnya dan cuba lagi.|[2,*]Satu lagi pemulangan telah diselesaikan sejak yang ini dibuka, dan :count baris tidak lagi muat dengan penghantaran itu. Edit pemulangan ini dan cuba lagi.',
    ],

    'validation' => [
        'over_return' => 'Hanya :remaining bagi baris ini masih boleh dipulangkan.',
    ],
];
