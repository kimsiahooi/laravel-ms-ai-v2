<?php

declare(strict_types=1);

return [
    'failed' => 'Butiran ini tidak sepadan dengan rekod kami.',
    'password' => 'Kata laluan yang diberikan tidak betul.',
    'throttle' => 'Terlalu banyak percubaan log masuk. Sila cuba lagi dalam :seconds saat.',

    'panel' => [
        'heading' => 'Stok anda, pesanan anda, satu ruang kerja.',
        'point_stock' => 'Setiap pergerakan setiap item, di setiap lokasi.',
        'point_orders' => 'Belian ke pengeluaran ke jualan, dalam satu rantaian.',
        'footer' => 'Log masuk ke :workspace.',
    ],

    'fields' => [
        'email' => 'Alamat e-mel',
        'email_placeholder' => 'anda@contoh.com',
        'password' => 'Kata laluan',
        'password_placeholder' => 'Kata laluan',
    ],

    'login' => [
        'head' => 'Log masuk',
        'title' => 'Log masuk ke ruang kerja anda',
        'description' => 'Masukkan e-mel dan kata laluan anda untuk meneruskan.',
        'forgot' => 'Lupa kata laluan?',
        'remember' => 'Kekalkan saya log masuk',
        'submit' => 'Log masuk',
        'submitting' => 'Sedang log masuk…',
    ],

    'forgot' => [
        'head' => 'Lupa kata laluan',
        'title' => 'Lupa kata laluan anda?',
        'description' => 'Masukkan e-mel anda dan kami akan menghantar pautan tetapan semula.',
        'submit' => 'Hantar pautan tetapan semula',
        'submitting' => 'Sedang menghantar…',
        'return' => 'Atau kembali ke',
        'login' => 'log masuk',
    ],

    'reset' => [
        'head' => 'Tetapkan semula kata laluan',
        'title' => 'Tetapkan semula kata laluan anda',
        'description' => 'Pilih kata laluan baharu untuk akaun anda.',
        'new_password' => 'Kata laluan baharu',
        'confirm_password' => 'Sahkan kata laluan',
        'submit' => 'Tetapkan semula kata laluan',
        'submitting' => 'Sedang menetapkan semula…',
    ],

    'confirm' => [
        'head' => 'Sahkan kata laluan',
        'title' => 'Sahkan kata laluan anda',
        'description' => 'Ini kawasan selamat. Sila sahkan kata laluan anda sebelum meneruskan.',
        'submit' => 'Sahkan kata laluan',
        'submitting' => 'Sedang mengesahkan…',
        'with_passkey' => 'Sahkan dengan kunci laluan',
        'or_password' => 'Atau sahkan dengan kata laluan',
    ],

    'verify' => [
        'sent' => 'Pautan pengesahan baharu telah dihantar ke alamat e-mel anda.',
        'head' => 'Sahkan e-mel',
        'title' => 'Sahkan alamat e-mel anda',
        'description' => 'Klik pautan yang baru kami hantar. Jika ia belum sampai, kami boleh hantar sekali lagi.',
        'resend' => 'Hantar semula e-mel pengesahan',
        'resending' => 'Sedang menghantar…',
        'log_out' => 'Log keluar',
    ],

    'two_factor' => [
        'head' => 'Pengesahan dua faktor',
        'code_title' => 'Kod pengesahan',
        'code_description' => 'Masukkan kod daripada aplikasi pengesah anda.',
        'code_toggle' => 'log masuk menggunakan kod pemulihan',
        'recovery_title' => 'Kod pemulihan',
        'recovery_description' => 'Masukkan salah satu kod pemulihan kecemasan anda.',
        'recovery_toggle' => 'log masuk menggunakan kod pengesahan',
        'recovery_placeholder' => 'Masukkan kod pemulihan',
        'continue' => 'Teruskan',
        'or' => 'atau anda boleh',
    ],

    'passkey' => [
        'authenticating' => 'Sedang mengesahkan…',
        'sign_in' => 'Log masuk dengan kunci laluan',
        'or_email' => 'Atau teruskan dengan e-mel',
    ],
];
