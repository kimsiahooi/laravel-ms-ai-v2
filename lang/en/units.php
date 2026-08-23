<?php

declare(strict_types=1);

/*
| Units of measure, keyed by the codes in App\Enums\Unit, and the dimensions in
| App\Enums\Dimension.
|
| The codes and their conversion factors live in the enum because they are data the
| server validates against and the stock engine does arithmetic with. The words live
| here because a unit's name is a user-facing string like any other.
|
| Two forms, on purpose. `symbol` is what sits beside a quantity, where anything longer
| than a few characters is noise. `name` is what the picker offers, where "t" alone is a
| guess — so it spells the unit out and repeats the symbol, and the person choosing sees
| exactly what will be stored.
*/

return [
    'dimension' => [
        'mass' => 'Mass',
        'volume' => 'Volume',
        'length' => 'Length',
        'count' => 'Count',
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
        'pcs' => 'pcs',
        'box' => 'box',
        'roll' => 'roll',
        'sheet' => 'sheet',
        'pair' => 'pair',
        'set' => 'set',
    ],

    'name' => [
        'g' => 'Gram (g)',
        'kg' => 'Kilogram (kg)',
        't' => 'Tonne (t)',
        'ml' => 'Millilitre (ml)',
        'l' => 'Litre (L)',
        'mm' => 'Millimetre (mm)',
        'cm' => 'Centimetre (cm)',
        'm' => 'Metre (m)',
        'pcs' => 'Piece (pcs)',
        'box' => 'Box',
        'roll' => 'Roll',
        'sheet' => 'Sheet',
        'pair' => 'Pair',
        'set' => 'Set',
    ],
];
