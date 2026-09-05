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
