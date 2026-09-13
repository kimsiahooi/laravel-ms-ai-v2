<?php

declare(strict_types=1);

/*
| Pemulangan belian — barang yang dihantar semula kepada pembekal, dan kredit yang terhasil.
| Lihat fail `en` untuk sebab setiap perkataan dipilih.
|
| "Baki" bukan "tersedia": had satu baris adalah tentang penghantaran ini, bukan tentang apa
| yang ada di rak. Rak ialah soalan berasingan, ditanya semasa pemulangan diselesaikan.
*/

return [
    'title' => 'Pemulangan belian',
    'subtitle' => 'Barang yang dihantar semula kepada pembekal, dikreditkan pada harga yang dicaj dalam penghantaran.',
    'search_placeholder' => 'Cari nombor pemulangan atau pesanan, pembekal atau nota…',

    'column' => [
        'number' => 'Pemulangan',
        'order' => 'Terhadap',
        'supplier' => 'Pembekal',
        'status' => 'Status',
        'reason' => 'Sebab',
        'total' => 'Kredit',
        'created' => 'Dibuka',
    ],

    'action' => [
        'new' => 'Pemulangan belian baharu',
        'edit' => 'Edit pemulangan',
    ],

    'filter' => [
        'status' => 'Status',
        'all_statuses' => 'Sebarang status',
        'reason' => 'Sebab',
        'all_reasons' => 'Sebarang sebab',
    ],

    'create' => [
        'title' => 'Pemulangan terhadap :number',
        'crumb' => 'Pemulangan baharu',
        'subtitle' => 'Nyatakan berapa banyak setiap baris yang dihantar akan dipulangkan. Harga diambil daripada pesanan, jadi kredit sepadan dengan apa yang dicaj.',
        'submit' => 'Simpan pemulangan',
        'submitting' => 'Menyimpan…',
    ],

    'edit' => [
        'title' => 'Edit :number',
        'crumb' => 'Edit',
        'subtitle' => 'Hanya pemulangan yang masih menunggu boleh diubah.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'order' => [
        'heading' => 'Mengkreditkan',
        'number' => 'Pesanan belian',
        'supplier' => 'Pembekal',
        'received' => 'Diterima',
        'locked' => 'Satu pemulangan mengkreditkan satu penghantaran, jadi ini tidak boleh diubah. Buka pemulangan baharu untuk mengkreditkan pesanan lain.',
    ],

    'field' => [
        'reason' => 'Sebab',
        'reason_placeholder' => 'Mengapa barang dipulangkan',
        'notes' => 'Nota',
        'notes_placeholder' => 'Rujukan, apa yang pembekal katakan, apa-apa yang patut diingat',
    ],

    'lines' => [
        'heading' => 'Apa yang dipulangkan',
        'description' => 'Setiap baris penghantaran yang masih ada baki untuk dipulangkan. Biarkan kuantiti kosong untuk mengecualikan baris itu.',
        'fill_all' => 'Pulangkan semua',
        'empty' => 'Semua yang ada pada pesanan ini telah pun dipulangkan.',
    ],

    'line' => [
        'item' => 'Bahan',
        'ordered' => 'Dihantar',
        'returned' => 'Dipulangkan',
        'remaining' => 'Baki',
        'quantity' => 'Dipulangkan kini',
        'quantity_placeholder' => 'cth. 2',
        'unit_cost' => 'Kos seunit',
        'discount' => 'Diskaun',
        'total' => 'Kredit baris',
    ],

    'summary' => [
        'order' => 'Pesanan belian',
        'supplier' => 'Pembekal',
        'currency' => 'Mata wang',
        'rate' => 'pada :rate',
        'reason' => 'Sebab',
        'raised_by' => 'Dibuka oleh',
        'notes' => 'Nota',
    ],

    'dialog' => [
        'delete' => [
            'title' => 'Padam :number?',
            'description' => 'Pemulangan ini dibuang dan kuantitinya boleh dipulangkan semula. Tiada apa-apa telah bergerak lagi, jadi tiada apa-apa yang diterbalikkan.',
            'submit' => 'Padam pemulangan',
            'submitting' => 'Memadam…',
        ],
    ],

    'empty' => [
        'title' => 'Belum ada pemulangan belian',
        'description' => 'Pemulangan dibuka terhadap satu penghantaran. Buka pesanan belian yang membawa barang itu dan mulakan dari sana.',
        'action' => 'Pergi ke pesanan yang diterima',
    ],

    'no_setup' => [
        'title' => 'Belum ada apa-apa yang diterima',
        'description' => 'Barang hanya boleh dipulangkan selepas ia tiba. Terima satu pesanan belian dan ia menjadi boleh dipulangkan.',
        'action' => 'Pergi ke pesanan belian',
    ],

    'no_match' => [
        'title' => 'Tiada pemulangan sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'toast' => [
        'created' => 'Pemulangan belian dibuka.',
        'updated' => 'Pemulangan belian dikemas kini.',
        'deleted' => 'Pemulangan belian dipadam.',
    ],

    'error' => [
        'not_pending' => 'Pemulangan ini telah pun diselesaikan atau dibatalkan.',
        'completed_locked' => 'Pemulangan yang telah selesai tidak boleh diubah atau dipadam.',
        'no_order' => 'Pesanan belian itu tidak boleh dipulangkan — ia mungkin telah dipadam, atau ia tidak pernah diterima.',
        'nothing_returnable' => 'Semua yang ada pada pesanan itu telah pun dipulangkan.',
    ],

    'validation' => [
        'over_return' => 'Hanya :remaining daripada baris ini yang masih boleh dipulangkan.',
    ],
];
