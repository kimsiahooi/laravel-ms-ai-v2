<?php

declare(strict_types=1);

/*
| Kategori produk — modul katalog pertama. Satu skrin: satu senarai, satu dialog di
| atasnya, dan satu pengesahan. Kroma yang dikongsi dengan setiap senarai lain
| (carian, penomboran, Batal, Edit, Padam) berada dalam common.php.
*/

return [
    'title' => 'Kategori',
    'subtitle' => 'Kumpulkan produk dalam katalog anda supaya senarai yang panjang kekal mudah dicari.',

    'search_placeholder' => 'Cari nama atau keterangan…',

    'column' => [
        'name' => 'Nama',
        'description' => 'Keterangan',
        'created' => 'Dicipta',
        'creator' => 'Dicipta oleh',
    ],

    'empty' => [
        'title' => 'Belum ada kategori',
        'description' => 'Kategori mengumpulkan produk anda. Cipta yang pertama dan ia akan sedia apabila anda menambah produk.',
    ],

    'no_match' => [
        'title' => 'Tiada kategori sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Kategori baharu',
        'title' => 'Kategori baharu',
        'description' => 'Namakan kumpulan tempat produk anda akan difailkan.',
        'submit' => 'Cipta kategori',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit kategori',
        'description' => 'Menamakan semula kategori akan mengemas kininya di semua tempat ia digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama',
        'name_placeholder' => 'cth. Pengikat',
        'description' => 'Keterangan',
        'description_placeholder' => 'Apa yang tergolong dalam kategori ini',
    ],

    'confirm' => [
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Produk yang sudah difailkan di bawah kategori ini mengekalkan datanya — ia cuma tidak lagi dikumpulkan mengikutnya. Tiada apa-apa lagi yang dibuang.',
        'delete_submit' => 'Padam kategori',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'created' => ':name telah dicipta.',
        'updated' => ':name telah dikemas kini.',
        'deleted' => ':name telah dipadam.',
    ],
];
