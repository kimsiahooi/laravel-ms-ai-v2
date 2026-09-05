<?php

declare(strict_types=1);

/*
| Bahan mentah — apa yang ruang kerja ini beli masuk dan gunakan. Kroma yang
| dikongsi dengan senarai lain (carian, penomboran, Batal, Edit, Padam) berada
| dalam common.php.
*/

return [
    'title' => 'Bahan mentah',
    'subtitle' => 'Apa yang anda beli masuk dan gunakan untuk menghasilkan barang jualan anda.',

    'search_placeholder' => 'Cari nama, SKU atau kod bar…',

    'filter' => [
        'unit' => 'Unit',
        'all_units' => 'Semua unit',
    ],

    'column' => [
        'name' => 'Bahan',
        'sku' => 'SKU',
        'unit' => 'Unit',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
    ],

    'empty' => [
        'title' => 'Belum ada bahan mentah',
        'description' => 'Tambah yang pertama dan ia akan sedia untuk diterima, dikira dan digunakan untuk membina produk.',
    ],

    'no_match' => [
        'title' => 'Tiada bahan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Bahan baharu',
        'title' => 'Bahan mentah baharu',
        'description' => 'Satu kod untuk merujuknya, dan unit yang anda gunakan untuk mengiranya.',
        'submit' => 'Cipta bahan',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit bahan mentah',
        'description' => 'Perubahan digunakan di semua tempat bahan ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama',
        'name_placeholder' => 'cth. Rod keluli 12mm',
        'sku' => 'SKU',
        'sku_placeholder' => 'cth. RM-001',
        'sku_hint' => 'Kod anda sendiri untuk bahan ini. Ia muncul pada pesanan belian dan senarai stok, dan tiada dua bahan boleh berkongsi kod yang sama.',
        'barcode' => 'Kod bar',
        'barcode_placeholder' => 'Imbas atau taip kod bar',
        'barcode_hint' => 'Diimbas untuk mencari bahan ini semasa kiraan stok, pergerakan dan pemindahan.',
        'unit' => 'Unit',
        'unit_placeholder' => 'Pilih unit',
        'unit_hint' => 'Apa yang anda gunakan untuk mengiranya. Setiap kuantiti yang direkodkan untuk bahan ini ialah bilangan unit ini, jadi pilih unit yang anda beli dan keluarkan.',
    ],

    'confirm' => [
        'blocked_title' => 'Tidak boleh memadam :name',
        'blocked_description' => '{1} Ia digunakan dalam senarai bahan untuk :products. Buang ia daripada senarai itu dahulu, barulah bahan ini boleh dipadam.|[2,*] Ia digunakan dalam senarai bahan untuk :count produk (:products). Buang ia daripada senarai tersebut dahulu, barulah bahan ini boleh dipadam.',
        'blocked_link' => '{1} Lihat produk ini|[2,*] Lihat semua :count produk',
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Stok yang sudah direkodkan untuk bahan ini mengekalkan sejarahnya — anda cuma tidak lagi boleh memilihnya untuk perkara baharu.',
        'delete_submit' => 'Padam bahan',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'in_use' => '{1} :name tidak boleh dipadam — ia digunakan dalam senarai bahan untuk :products.|[2,*] :name tidak boleh dipadam — ia digunakan dalam senarai bahan untuk :count produk (:products).',
        'created' => ':name telah dicipta.',
        'updated' => ':name telah dikemas kini.',
        'deleted' => ':name telah dipadam.',
    ],
];
