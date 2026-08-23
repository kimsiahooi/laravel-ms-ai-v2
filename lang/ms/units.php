<?php

declare(strict_types=1);

/*
| Unit ukuran, dikunci mengikut kod dalam App\Enums\Unit dan dimensi dalam
| App\Enums\Dimension.
|
| Kod dan faktor penukarannya berada dalam enum kerana ia data yang disahkan oleh
| pelayan dan digunakan untuk pengiraan stok. Perkataannya berada di sini kerana nama
| unit ialah teks yang dilihat pengguna, sama seperti yang lain.
*/

return [
    'dimension' => [
        'mass' => 'Jisim',
        'volume' => 'Isi padu',
        'length' => 'Panjang',
        'count' => 'Bilangan',
    ],

    'symbol' => [
        'g' => 'g',
        'kg' => 'kg',
        't' => 't',
        'ml' => 'ml',
        'l' => 'L',
        'mm' => 'mm',
        'cm' => 'cm',
        'm' => 'm',
        'pcs' => 'unit',
        'box' => 'kotak',
        'roll' => 'gulung',
        'sheet' => 'helai',
        'pair' => 'pasang',
        'set' => 'set',
    ],

    'name' => [
        'g' => 'Gram (g)',
        'kg' => 'Kilogram (kg)',
        't' => 'Tan (t)',
        'ml' => 'Mililiter (ml)',
        'l' => 'Liter (L)',
        'mm' => 'Milimeter (mm)',
        'cm' => 'Sentimeter (cm)',
        'm' => 'Meter (m)',
        'pcs' => 'Unit',
        'box' => 'Kotak',
        'roll' => 'Gulung',
        'sheet' => 'Helai',
        'pair' => 'Pasang',
        'set' => 'Set',
    ],
];
