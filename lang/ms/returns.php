<?php

declare(strict_types=1);

/*
| Perkataan yang dikongsi oleh kedua-dua jenis pemulangan — belian dan jualan. Lihat fail `en`
| untuk sebab setiap perkataan dipilih.
*/

return [
    // App\Enums\ReturnStatus.
    'status' => [
        'pending' => 'Menunggu',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ],

    // App\Enums\ReturnReason.
    'reason' => [
        'damaged' => 'Rosak',
        'wrong_item' => 'Item salah',
        'quality' => 'Tidak menepati spesifikasi',
        'surplus' => 'Lebihan daripada keperluan',
        'other' => 'Lain-lain',
    ],
];
