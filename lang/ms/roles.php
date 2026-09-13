<?php

declare(strict_types=1);

/*
| Peranan — apa yang boleh dicapai oleh sekumpulan orang, dan skrin tempat perkara itu
| diputuskan. Lihat fail `en` untuk sebab setiap perkataan dipilih.
|
| Nama peranan itu sendiri tidak pernah diterjemah: "Administrator" datang daripada penyemai,
| dan setiap peranan lain ialah perkataan yang ditaip oleh ruang kerja ini sendiri.
*/

return [
    'title' => 'Peranan',
    'subtitle' => 'Peranan menentukan skrin mana yang boleh dicapai oleh pemegangnya. Berikan peranan paling sempit yang masih membolehkan mereka bekerja.',

    'action' => [
        'new' => 'Peranan baharu',
    ],

    'card' => [
        'built_in' => 'Terbina dalam',
        'locked' => 'Sentiasa memegang segala-galanya. Ia tidak boleh diedit atau dipadam, supaya ruang kerja tidak boleh mengunci dirinya sendiri.',
        'permissions' => '{0}Tidak memberi apa-apa|{1}1 kebenaran|[2,*]:count kebenaran',
        'holders' => '{0}Tiada sesiapa memegangnya|{1}1 orang memegangnya|[2,*]:count orang memegangnya',
    ],

    'create' => [
        'title' => 'Peranan baharu',
        'crumb' => 'Peranan baharu',
        'subtitle' => 'Namakannya mengikut tugas seseorang, kemudian tandakan apa yang perlu dicapai oleh tugas itu.',
        'submit' => 'Cipta peranan',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'crumb' => 'Edit',
        'subtitle' => 'Perubahan berkuat kuasa pada kali berikutnya setiap orang memuatkan halaman.',
        'holders' => '{0}Belum ada sesiapa memegang peranan ini.|{1}Seorang memegang peranan ini.|[2,*]:count orang memegang peranan ini.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'field' => [
        'name' => 'Nama peranan',
        'name_placeholder' => 'cth. Kerani stok',
        'name_hint' => 'Tugasnya, bukan orangnya. Inilah yang ditawarkan oleh skrin Pengguna apabila seseorang diberikan peranan.',
    ],

    'matrix' => [
        'heading' => 'Apa yang dicapai oleh peranan ini',
        'description' => 'Setiap kumpulan ialah satu skrin. “Lihat” yang meletakkannya dalam bar sisi seseorang — tanpanya, selebihnya dalam kumpulan itu tiada apa-apa untuk dikendalikan.',
        'select_all' => 'Pilih semua',
        'clear_all' => 'Kosongkan semua',
        'group_all' => 'Pilih semua dalam :screen',
        'group_count' => ':selected daripada :total',
        'selected' => ':selected daripada :total dipilih',
    ],

    'dialog' => [
        'delete' => [
            'title' => 'Padam :name?',
            'description' => 'Peranan ini dibuang terus. Tiada sesiapa memegangnya, jadi tiada sesiapa kehilangan akses — tetapi apa yang anda tandakan di sini perlu ditandakan semula pada peranan baharu.',
            'submit' => 'Padam peranan',
            'submitting' => 'Memadam…',
        ],
    ],

    'toast' => [
        'created' => ':name dicipta.',
        'updated' => ':name dikemas kini.',
        'deleted' => ':name dipadam.',
    ],

    'error' => [
        'in_use' => '{1}:name masih dipegang oleh seorang, termasuk rakan sekerja yang dinyahaktifkan. Pindahkan mereka ke peranan lain dahulu.|[2,*]:name masih dipegang oleh :count orang, termasuk rakan sekerja yang dinyahaktifkan. Pindahkan mereka ke peranan lain dahulu.',
    ],

    'validation' => [
        'permissions' => 'Pilih sekurang-kurangnya satu perkara yang boleh dicapai oleh peranan ini.',
    ],
];
