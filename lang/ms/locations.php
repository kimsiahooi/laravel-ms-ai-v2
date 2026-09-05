<?php

declare(strict_types=1);

/*
| Rujuk lang/en/locations.php untuk nota tentang modul ini.
*/

return [
    'title' => 'Tapak',
    'subtitle' => 'Tempat anda beroperasi. Setiap satu memegang gudang tempat stok sebenarnya disimpan.',

    'search_placeholder' => 'Cari nama, kod atau alamat…',

    'column' => [
        'name' => 'Tapak',
        'code' => 'Kod',
        'address' => 'Alamat',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
    ],

    'empty' => [
        'title' => 'Belum ada tapak',
        'description' => 'Tapak ialah cawangan, kedai atau kilang. Tambah yang pertama dan anda boleh mula memberikannya gudang.',
    ],

    'no_match' => [
        'title' => 'Tiada tapak sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Tapak baharu',
        'title' => 'Tapak baharu',
        'description' => 'Tempat anda beroperasi. Hanya nama diperlukan.',
        'submit' => 'Cipta tapak',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit tapak',
        'description' => 'Perubahan digunakan di semua tempat tapak ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama',
        'name_placeholder' => 'cth. Cawangan Pulau Pinang',
        'code' => 'Kod',
        'code_placeholder' => 'cth. PEN',
        'code_hint' => 'Kod ringkas anda sendiri untuk tapak ini. Ia muncul pada pemindahan dan laporan, dan tiada dua tapak boleh berkongsi satu kod.',
        'address' => 'Alamat',
        'address_placeholder' => 'Jalan, bandar, poskod',
    ],

    'confirm' => [
        'blocked_title' => 'Tidak boleh memadam :name',
        'blocked_description' => '{1} Masih ada gudang di tapak ini: :warehouses. Pindahkan atau buang ia dahulu, barulah tapak ini boleh dipadam.|[2,*] Masih ada :count gudang di tapak ini (:warehouses). Pindahkan atau buang ia dahulu, barulah tapak ini boleh dipadam.',
        'blocked_link' => '{1} Lihat gudang ini|[2,*] Lihat semua :count gudang',
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Pergerakan yang telah direkodkan di tapak ini mengekalkan sejarahnya — anda cuma tidak boleh memilihnya untuk perkara baharu.',
        'delete_submit' => 'Padam tapak',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'in_use' => '{1} :name tidak boleh dipadam — masih ada gudang di atasnya: :warehouses.|[2,*] :name tidak boleh dipadam — masih ada :count gudang di atasnya (:warehouses).',
        'created' => ':name dicipta.',
        'updated' => ':name dikemas kini.',
        'deleted' => ':name dipadam.',
    ],
];
