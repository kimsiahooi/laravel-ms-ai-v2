<?php

declare(strict_types=1);

/*
| The workspace shell — chrome a signed-in tenant user sees on every screen. Module
| strings live in that module's own file.
*/

return [
    // Fallback for the workspace's own name, used only before one is known.
    'name' => 'Ruang kerja',

    'nav' => [
        // Sidebar group headings only. An entry names itself from its own module
        // file, so 'Categories' has exactly one definition.
        'catalog' => 'Katalog',
        'dashboard' => 'Papan pemuka',
        'orders' => 'Pesanan',
        'stock' => 'Stok',
        'settings' => 'Tetapan akaun',
        'sign_out' => 'Log keluar',
    ],
];
