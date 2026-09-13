<?php

declare(strict_types=1);

/*
| Pengguna — lihat lang/en/users.php untuk nota penuh.
*/

return [
    'title' => 'Pengguna',
    'subtitle' => 'Siapa yang boleh log masuk ke ruang kerja ini, dan peranan yang menentukan apa yang mereka capai.',
    'search_placeholder' => 'Cari nama atau e-mel…',

    'column' => [
        'name' => 'Nama',
        'email' => 'E-mel',
        'role' => 'Peranan',
        'status' => 'Status',
        'created' => 'Ditambah',
    ],

    'status' => [
        'active' => 'Aktif',
        'deactivated' => 'Dinyahaktifkan',
        'temporary_password' => 'Kata laluan sementara',
    ],

    'filter' => [
        'status' => 'Status',
        'active' => 'Aktif',
        'deactivated' => 'Dinyahaktifkan',
        'all' => 'Semua orang',
    ],

    'action' => [
        'new' => 'Tambah orang',
        'edit' => 'Edit',
        'deactivate' => 'Nyahaktifkan',
        'restore' => 'Aktifkan semula',
    ],

    'create' => [
        'title' => 'Tambah orang',
        'description' => 'Mereka log masuk dengan kata laluan yang anda tetapkan di sini, dan diminta menggantikannya pada kali pertama.',
        'submit' => 'Tambah pengguna',
        'submitting' => 'Menambah…',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'description' => 'Biarkan kata laluan kosong untuk mengekalkan yang sedia ada. Menetapkan satu akan meminta mereka menggantikannya semula.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama',
        'name_placeholder' => 'Nama penuh mereka',
        'email' => 'E-mel',
        'email_placeholder' => 'nama@syarikat.com',
        'email_hint' => 'Ini yang mereka guna untuk log masuk.',
        'role' => 'Peranan',
        'role_placeholder' => 'Apa yang mereka boleh capai',
        'role_search' => 'Cari peranan…',
        'role_empty' => 'Tiada peranan sepadan.',
        'password' => 'Kata laluan sementara',
        'password_placeholder' => 'Sekurang-kurangnya 8 aksara',
        'password_hint' => 'Anda perlu memberitahu mereka kata laluan ini. Mereka diminta menggantikannya pada log masuk pertama.',
        'password_optional_hint' => 'Biarkan kosong untuk mengekalkan kata laluan semasa mereka.',
        'password_confirmation' => 'Sahkan kata laluan',
    ],

    'dialog' => [
        'deactivate' => [
            'title' => 'Nyahaktifkan :name?',
            'description' => 'Mereka tidak lagi boleh log masuk. Segala yang telah mereka lakukan kekal atas nama mereka, dan anda boleh mengaktifkan semula pada bila-bila masa.',
            'submit' => 'Nyahaktifkan',
            'submitting' => 'Menyahaktifkan…',
        ],
    ],

    'empty' => [
        'title' => 'Setakat ini anda sahaja',
        'description' => 'Tambah rakan sekerja dan berikan peranan yang hanya mencapai bahagian ruang kerja yang mereka perlukan.',
    ],

    'no_match' => [
        'title' => 'Tiada sesiapa sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'toast' => [
        'created' => ':name kini boleh log masuk.',
        'updated' => ':name dikemas kini.',
        'deactivated' => ':name tidak lagi boleh log masuk.',
        'restored' => ':name boleh log masuk semula.',
    ],

    'error' => [
        'last_administrator' => 'Ruang kerja ini memerlukan sekurang-kurangnya seorang pentadbir, dan mereka satu-satunya yang tinggal.',
        'last_administrator_self' => 'Anda satu-satunya pentadbir. Berikan peranan Administrator kepada orang lain sebelum memadam akaun anda, atau ruang kerja ini tiada sesiapa yang boleh membetulkan apa-apa.',
        'not_yourself' => 'Anda tidak boleh menyahaktifkan akaun anda sendiri.',
        'must_change_password' => 'Pilih kata laluan anda sendiri sebelum meneruskan.',
    ],

    'validation' => [
        'email_deactivated' => 'Alamat itu milik orang yang telah dinyahaktifkan. Aktifkan semula mereka daripada menambah akaun baharu.',
        'last_administrator' => 'Mereka satu-satunya pentadbir. Berikan peranan itu kepada orang lain dahulu.',
    ],
];
