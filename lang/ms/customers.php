<?php

declare(strict_types=1);

/*
| Pelanggan — tempat ruang kerja ini menjual. Lebih luas daripada pembekal kerana
| invois mesti dialamatkan kepada entiti sah.
*/

return [
    'title' => 'Pelanggan',
    'subtitle' => 'Kepada siapa anda menjual, dan butiran yang perlu ada pada invois.',

    'search_placeholder' => 'Cari nama, kenalan, e-mel, TIN atau nota…',

    'column' => [
        'name' => 'Pelanggan',
        'email' => 'E-mel',
        'location' => 'Lokasi',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
    ],

    'empty' => [
        'title' => 'Belum ada pelanggan',
        'description' => 'Tambah yang pertama dan ia akan sedia untuk dipilih apabila anda membuat pesanan jualan.',
    ],

    'no_match' => [
        'title' => 'Tiada pelanggan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Pelanggan baharu',
        'title' => 'Pelanggan baharu',
        'description' => 'Hanya nama yang diperlukan. Butiran cukai dan alamat adalah untuk invois, dan boleh ditambah kemudian.',
        'submit' => 'Cipta pelanggan',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit pelanggan',
        'description' => 'Perubahan digunakan di semua tempat pelanggan ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'group' => [
        'identity' => 'Siapa mereka',
        'tax' => 'Identiti cukai',
        'tax_hint' => 'Diperlukan pada e-invois, pilihan di sini — isikan apabila anda ada.',
        'address' => 'Alamat pengebilan',
    ],

    'field' => [
        'name' => 'Nama syarikat',
        'name_placeholder' => 'cth. Meridian Engineering Sdn Bhd',
        'contact_person' => 'Orang untuk dihubungi',
        'contact_person_placeholder' => 'Siapa yang anda uruskan',
        'email' => 'E-mel',
        'email_placeholder' => 'accounts@example.com',
        'phone' => 'Telefon',
        'phone_placeholder' => '+60 3 1234 5678',
        'tin' => 'TIN',
        'tin_placeholder' => 'Nombor pengenalan cukai',
        'registration_no' => 'Nombor pendaftaran',
        'registration_no_placeholder' => 'SSM (MY) atau UEN (SG)',
        'sst_registration_no' => 'Nombor SST / GST',
        'sst_registration_no_placeholder' => 'Jika mereka berdaftar',
        'address' => 'Alamat jalan',
        'address_placeholder' => 'Bangunan, jalan, unit',
        'city' => 'Bandar',
        'city_placeholder' => 'cth. Shah Alam',
        'postcode' => 'Poskod',
        'postcode_placeholder' => 'cth. 40150',
        'state_code' => 'Kod negeri',
        'state_code_placeholder' => 'cth. 10',
        'country_code' => 'Negara',
        'country_code_placeholder' => 'Pilih negara',
        'notes' => 'Nota',
        'notes_placeholder' => 'Terma kredit, arahan penghantaran, apa-apa yang perlu diingat',
    ],

    'confirm' => [
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Pesanan jualan dan invois yang sudah dibuat untuk pelanggan ini mengekalkan rekodnya — anda cuma tidak lagi boleh memilihnya untuk yang baharu.',
        'delete_submit' => 'Padam pelanggan',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'created' => ':name telah dicipta.',
        'updated' => ':name telah dikemas kini.',
        'deleted' => ':name telah dipadam.',
    ],
];
