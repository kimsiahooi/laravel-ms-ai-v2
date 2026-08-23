<?php

declare(strict_types=1);

/*
| Country names, keyed by the ISO 3166-1 alpha-2 codes in App\Enums\Country.
|
| The codes live in the enum because they are data the server validates against and
| the e-invoice builders switch on. The names live here because a country's name is a
| user-facing string like any other — shipping English labels inside the page props
| would put one language's words in the data.
*/

return [
    'MY' => 'Malaysia',
    'SG' => 'Singapore',
];
