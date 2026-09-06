<?php

declare(strict_types=1);

/*
| The workspace's own settings — the money half of them. What the books are kept in,
| what an order may be raised in, what tax to charge, and what documents are called.
|
| Apart from settings.php, which holds the screens that belong to the PERSON signed in.
| These belong to the business, and changing one changes what every colleague sees.
|
| Currency codes are not translated: ISO 4217 is the same in every language, so only
| the name beside the code is a string.
*/

return [
    'title' => 'Business settings',
    'head' => 'Business settings',
    'subtitle' => 'What this workspace trades in, and how its documents are named. Changing these affects everyone here.',

    'money' => [
        'title' => 'Money',
        'description' => 'The currency the books are kept in, the ones an order may be raised in, and the tax charged on it.',
    ],

    'documents' => [
        'title' => 'Document numbers',
        'description' => 'How purchase orders, sales orders and their returns are numbered.',
    ],

    'field' => [
        'base_currency' => 'Base currency',
        'base_currency_placeholder' => 'Choose a currency',
        'base_currency_hint' => 'What the books are kept in. An order may still be raised in another currency and carries its own exchange rate.',

        'currencies' => 'Currencies an order may use',
        'currencies_hint' => 'The base currency is always available, so it cannot be unticked here.',

        'tax_rate' => 'Tax rate',
        'tax_rate_placeholder' => 'e.g. 6',
        'tax_rate_hint' => 'A percentage, between 0 and 100. A new order starts from this; one already raised keeps the rate it was raised under.',

        'tax_label' => 'Tax label',
        'tax_label_placeholder' => 'e.g. SST',
        'tax_label_hint' => 'What the tax is called on a document. Stored rather than translated — it is a legal term, and it does not change with the reader’s language.',

        'purchase_order_prefix' => 'Purchase order prefix',
        'purchase_return_prefix' => 'Purchase return prefix',
        'sales_order_prefix' => 'Sales order prefix',
        'sales_return_prefix' => 'Sales return prefix',
        'prefix_placeholder' => 'e.g. PO',
        'prefix_hint' => 'Letters, numbers and hyphens. A purchase order numbered with “PO” reads PO-2026-0001.',

        'number_reset' => 'Restart numbering',
        'number_reset_placeholder' => 'Choose when the count restarts',
        'number_reset_hint' => 'Restarting each year puts the year in the number and counts from one again. Never counts on forever, which is what a business migrating from another system usually needs.',

        'financial_year_start_month' => 'Financial year starts in',
        'financial_year_start_month_placeholder' => 'Choose a month',
        'financial_year_start_month_hint' => 'Only used when numbering restarts each year. A year is labelled by the month it began in, so April 2025 to March 2026 numbers as 2025 throughout.',
    ],

    'number_reset' => [
        'yearly' => 'Each financial year',
        'never' => 'Never',
    ],

    'currency' => [
        'myr' => 'MYR — Malaysian ringgit',
        'sgd' => 'SGD — Singapore dollar',
        'usd' => 'USD — US dollar',
        'eur' => 'EUR — Euro',
        'cny' => 'CNY — Chinese yuan',
    ],

    'month' => [
        'january' => 'January',
        'february' => 'February',
        'march' => 'March',
        'april' => 'April',
        'may' => 'May',
        'june' => 'June',
        'july' => 'July',
        'august' => 'August',
        'september' => 'September',
        'october' => 'October',
        'november' => 'November',
        'december' => 'December',
    ],

    'validation' => [
        'prefix' => 'A prefix may use letters, numbers and hyphens only.',
    ],

    'action' => [
        'save' => 'Save settings',
        'saving' => 'Saving…',
    ],

    'toast' => [
        'saved' => 'Business settings saved.',
    ],
];
