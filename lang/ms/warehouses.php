<?php

declare(strict_types=1);

/*
| Rujuk lang/en/warehouses.php untuk nota tentang modul ini.
*/

return [
    'title' => 'Gudang',
    'subtitle' => 'Bangunan tempat stok anda disimpan. Setiap satu milik sebuah tapak.',

    'search_placeholder' => 'Cari nama, kod atau alamat…',

    'filter' => [
        'site' => 'Tapak',
        'all_sites' => 'Mana-mana tapak',
        'sites_selected' => 'Mana-mana daripada :count tapak',
        'site_hint' => '[0,1] Tandakan lebih daripada satu untuk meluaskan carian — gudang hanya perlu berada di salah satu daripadanya.|[2,*] Menunjukkan gudang di mana-mana daripada :count tapak ini, bukan gudang yang entah bagaimana berada di kesemuanya.',
        'site_search' => 'Cari tapak…',
        'site_empty' => 'Tiada tapak sepadan.',
    ],

    'column' => [
        'name' => 'Gudang',
        'code' => 'Kod',
        'site' => 'Tapak',
        'address' => 'Alamat',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
        'view_site' => 'Lihat :name dalam tapak',
    ],

    'empty' => [
        'title' => 'Belum ada gudang',
        'description' => 'Gudang ialah tempat stok sebenarnya disimpan. Tambah yang pertama dan anda boleh mula memindahkan stok ke dalamnya.',
    ],

    'no_match' => [
        'title' => 'Tiada gudang sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'no_sites' => [
        'title' => 'Tambah tapak dahulu',
        'description' => 'Setiap gudang milik sebuah tapak, jadi belum ada tempat untuk melekatkannya.',
        'action' => 'Pergi ke tapak',
    ],

    'create' => [
        'trigger' => 'Gudang baharu',
        'title' => 'Gudang baharu',
        'description' => 'Tempat stok disimpan, dan tapak tempat ia berdiri.',
        'submit' => 'Cipta gudang',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit gudang',
        'description' => 'Perubahan digunakan di semua tempat gudang ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'site' => 'Tapak',
        'site_placeholder' => 'Pilih tapak',
        'site_search' => 'Cari tapak…',
        'site_empty' => 'Tiada tapak sepadan.',
        'site_hint' => 'Tempat bangunan ini berdiri. Memindahkan gudang antara tapak turut memindahkan stoknya.',
        'name' => 'Nama',
        'name_placeholder' => 'cth. Stor utama',
        'code' => 'Kod',
        'code_placeholder' => 'cth. PEN-A',
        'code_hint' => 'Kod ringkas anda sendiri untuk gudang ini. Ia muncul pada pemindahan dan laporan, dan tiada dua gudang boleh berkongsi satu kod.',
        'address' => 'Alamat',
        'address_placeholder' => 'Jalan, bandar, poskod',
    ],

    /*
    | Skrin butiran — apa yang ada dalam satu gudang, dan bila setiap item perlu
    | ditambah semula. Hanya paras pesanan semula boleh diubah di sini.
    */
    'detail' => [
        'search_placeholder' => 'Cari item atau SKU…',
        'view_movements' => 'Lihat pergerakan',

        'in_stock' => 'Item ada stok',
        'in_stock_hint' => 'ada di rak sekarang',
        'needs_reorder' => 'Perlu pesan semula',
        'needs_reorder_hint' => 'pada atau bawah paras yang ditetapkan di sini',

        'column' => [
            'item' => 'Item',
            'sku' => 'SKU',
            'type' => 'Jenis',
            'on_hand' => 'Ada',
            'level' => 'Paras pesanan semula',
        ],

        'badge' => 'Pesan semula',

        'level_for' => 'Paras pesanan semula untuk :name',
        'level_placeholder' => 'Tidak ditetapkan',
        'level_hint' => 'Taip paras di mana item ini perlu ditambah semula. Biarkan kosong jika tiada amaran.',

        'filter' => [
            'show' => 'Papar',
            'stocked' => 'Dalam gudang ini',
            'attention' => 'Perlu pesan semula',
            'all' => 'Semua item',
        ],

        'empty' => [
            'title' => 'Katalog masih kosong',
            'description' => 'Tambah produk atau bahan mentah dahulu — kemudian barulah ia boleh dipindahkan masuk dan diberi paras pesanan semula.',
            'action' => 'Pergi ke produk',
        ],

        'no_stock' => [
            'title' => 'Belum ada apa-apa dalam gudang ini',
            'description' => 'Pindahkan stok masuk dan ia akan muncul di sini. Anda juga boleh tetapkan paras pesanan semula untuk sesuatu sebelum ia tiba.',
            'action' => 'Rekod pergerakan',
            'action_all' => 'Papar semua item',
        ],

        'no_match' => [
            'title' => 'Tiada item sepadan',
            'description' => 'Tiada apa-apa dalam gudang ini sepadan dengan “:term”. Tukar Papar kepada semua item untuk mencari seluruh katalog.',
        ],
    ],

    'confirm' => [
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Pergerakan yang telah direkodkan melalui gudang ini mengekalkan sejarahnya — anda cuma tidak boleh memilihnya untuk perkara baharu.',
        'delete_submit' => 'Padam gudang',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'created' => ':name dicipta.',
        'updated' => ':name dikemas kini.',
        'deleted' => ':name dipadam.',
    ],
];
