<?php

declare(strict_types=1);

/*
| Pembekal — tempat ruang kerja ini membeli. Kroma yang dikongsi dengan senarai
| lain (carian, penomboran, Batal, Edit, Padam) berada dalam common.php.
*/

return [
    'title' => 'Pembekal',
    'subtitle' => 'Tempat anda membeli, dan cara menghubungi mereka.',

    'search_placeholder' => 'Cari nama, kenalan, e-mel atau nota…',

    'column' => [
        'name' => 'Pembekal',
        'email' => 'E-mel',
        'phone' => 'Telefon',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
    ],

    'empty' => [
        'title' => 'Belum ada pembekal',
        'description' => 'Tambah yang pertama dan ia akan sedia untuk dipilih apabila anda membuat pesanan belian.',
    ],

    'no_match' => [
        'title' => 'Tiada pembekal sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Pembekal baharu',
        'title' => 'Pembekal baharu',
        'description' => 'Hanya nama yang diperlukan — selebihnya boleh ditambah kemudian.',
        'submit' => 'Cipta pembekal',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit pembekal',
        'description' => 'Perubahan digunakan di semua tempat pembekal ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama syarikat',
        'name_placeholder' => 'cth. Acme Steel Sdn Bhd',
        'contact_person' => 'Orang untuk dihubungi',
        'contact_person_placeholder' => 'Siapa yang anda uruskan',
        'email' => 'E-mel',
        'email_placeholder' => 'orders@example.com',
        'phone' => 'Telefon',
        'phone_placeholder' => '+60 3 1234 5678',
        'tax_id' => 'Nombor cukai',
        'tax_id_placeholder' => 'Nombor pendaftaran atau SST',
        'address' => 'Alamat',
        'address_placeholder' => 'Ke mana penghantaran dan invois dihantar',
        'notes' => 'Nota',
        'notes_placeholder' => 'Terma pembayaran, tempoh menunggu, apa-apa yang perlu diingat',
    ],

    'confirm' => [
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Pesanan belian yang sudah dibuat dengan pembekal ini mengekalkan rekodnya — anda cuma tidak lagi boleh memilihnya untuk pesanan baharu.',
        'delete_submit' => 'Padam pembekal',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'created' => ':name telah dicipta.',
        'updated' => ':name telah dikemas kini.',
        'deleted' => ':name telah dipadam.',
    ],
];
