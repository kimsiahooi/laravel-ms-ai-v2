<?php

declare(strict_types=1);

return [
    'nav' => [
        'profile' => 'Profil',
        'security' => 'Keselamatan',
        'appearance' => 'Penampilan',
    ],

    'heading' => [
        'title' => 'Tetapan',
        'description' => 'Urus profil dan tetapan akaun anda',
        'nav_label' => 'Bahagian tetapan',
    ],

    'profile' => [
        'head' => 'Tetapan profil',
        'title' => 'Profil',
        'description' => 'Kemas kini nama dan alamat e-mel anda',
        'name' => 'Nama',
        'name_placeholder' => 'Nama penuh',
        'email' => 'Alamat e-mel',
        'email_placeholder' => 'Alamat e-mel',
        'unverified' => 'Alamat e-mel anda belum disahkan.',
        'resend' => 'Klik di sini untuk menghantar semula e-mel pengesahan.',
        'sent' => 'Pautan pengesahan baharu telah dihantar ke alamat e-mel anda.',
        'save' => 'Simpan',
    ],

    'security' => [
        'head' => 'Tetapan keselamatan',
        'title' => 'Kemas kini kata laluan',
        'description' => 'Gunakan kata laluan yang panjang dan rawak untuk memastikan akaun anda selamat',
        'current' => 'Kata laluan semasa',
        'new' => 'Kata laluan baharu',
        'confirm' => 'Sahkan kata laluan',
        'save' => 'Simpan',
    ],

    'appearance' => [
        'head' => 'Tetapan penampilan',
        'title' => 'Penampilan',
        'description' => 'Pilih rupa aplikasi pada peranti ini',
    ],

    'delete' => [
        'title' => 'Padam akaun',
        'description' => 'Padam akaun anda dan semua datanya',
        'warning' => 'Amaran',
        'warning_body' => 'Sila teruskan dengan berhati-hati, ini tidak boleh dibatalkan.',
        'button' => 'Padam akaun',
        'confirm_title' => 'Adakah anda pasti mahu memadam akaun anda?',
        'confirm_body' => 'Setelah akaun anda dipadam, semua datanya turut dipadam secara kekal. Masukkan kata laluan anda untuk mengesahkan.',
    ],

    'two_factor' => [
        'title' => 'Pengesahan dua faktor',
        'description' => 'Tambah langkah kedua semasa log masuk',
        'enabled_body' => 'Anda akan diminta kod semasa log masuk. Dapatkannya daripada aplikasi pengesah pada telefon anda.',
        'disabled_body' => 'Setelah didayakan, anda akan diminta kod semasa log masuk. Kod itu datang daripada aplikasi pengesah pada telefon anda.',
        'disable' => 'Nyahdayakan',
        'continue_setup' => 'Teruskan persediaan',
        'enable' => 'Dayakan',
    ],

    'passkeys' => [
        'removing' => 'Sedang membuang…',
        'register' => 'Daftar kunci laluan',
        'registering' => 'Sedang mendaftar…',
        'title' => 'Kunci laluan',
        'description' => 'Log masuk tanpa kata laluan',
        'empty_title' => 'Belum ada kunci laluan',
        'empty_body' => 'Tambah satu untuk log masuk tanpa kata laluan.',
        'add' => 'Tambah kunci laluan',
        'name' => 'Nama kunci laluan',
        'name_placeholder' => 'cth. MacBook Pro, iPhone',
        'name_hint' => 'Nama membantu anda mengenal pasti kunci laluan ini kemudian.',
        'unsupported' => 'Kunci laluan tidak disokong dalam pelayar ini.',
        'remove' => 'Buang',
        'remove_title' => 'Buang kunci laluan',
        'remove_body' => 'Buang kunci laluan “:name”? Anda tidak lagi boleh log masuk dengannya.',
        'added' => 'Ditambah :when',
        'last_used' => 'Terakhir digunakan :when',
    ],

    'recovery' => [
        'title' => 'Kod pemulihan',
        'body' => 'Kod pemulihan membolehkan anda masuk semula jika anda kehilangan pengesah anda. Simpan dalam pengurus kata laluan.',
        'view_codes' => 'Lihat kod pemulihan',
        'hide_codes' => 'Sembunyikan kod pemulihan',
        'regenerate' => 'Jana semula kod',
        'note' => 'Setiap kod berfungsi sekali dan hilang selepas digunakan. Jika habis, jana semula di atas.',
    ],

    'setup' => [
        'enable_title' => 'Dayakan pengesahan dua faktor',
        'enable_description' => 'Untuk menyelesaikan pendayaan pengesahan dua faktor, imbas kod QR atau masukkan kunci persediaan dalam aplikasi pengesah anda',
        'verify_title' => 'Sahkan kod pengesahan',
        'verify_description' => 'Masukkan kod 6 digit daripada aplikasi pengesah anda',
        'enabled_title' => 'Pengesahan dua faktor didayakan',
        'enabled_description' => 'Pengesahan dua faktor kini didayakan. Imbas kod QR atau masukkan kunci persediaan dalam aplikasi pengesah anda.',
        'continue' => 'Teruskan',
        'manual' => 'atau masukkan kod secara manual',
        'back' => 'Kembali',
        'confirm' => 'Sahkan',
    ],
];
