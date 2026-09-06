<?php

declare(strict_types=1);

return [
    'title' => 'Tetapan perniagaan',
    'head' => 'Tetapan perniagaan',
    'subtitle' => 'Apa yang didagangkan oleh ruang kerja ini, dan bagaimana dokumennya dinamakan. Perubahan di sini menjejaskan semua orang di sini.',

    'money' => [
        'title' => 'Wang',
        'description' => 'Mata wang yang digunakan untuk pembukuan, mata wang yang boleh digunakan bagi sesuatu pesanan, dan cukai yang dikenakan padanya.',
    ],

    'documents' => [
        'title' => 'Nombor dokumen',
        'description' => 'Bagaimana pesanan belian, pesanan jualan dan pemulangannya dinomborkan.',
    ],

    'field' => [
        'base_currency' => 'Mata wang asas',
        'base_currency_placeholder' => 'Pilih mata wang',
        'base_currency_hint' => 'Mata wang yang digunakan untuk pembukuan. Sesuatu pesanan masih boleh dibuat dalam mata wang lain dan membawa kadar pertukarannya sendiri.',

        'currencies' => 'Mata wang yang boleh digunakan bagi sesuatu pesanan',
        'currencies_hint' => 'Mata wang asas sentiasa tersedia, jadi ia tidak boleh dinyahtanda di sini.',

        'tax_rate' => 'Kadar cukai',
        'tax_rate_placeholder' => 'cth. 6',
        'tax_rate_hint' => 'Peratusan, antara 0 dan 100. Pesanan baharu bermula daripada nilai ini; pesanan yang telah dibuat mengekalkan kadar semasa ia dibuat.',

        'tax_label' => 'Label cukai',
        'tax_label_placeholder' => 'cth. SST',
        'tax_label_hint' => 'Nama cukai pada dokumen. Disimpan dan bukan diterjemah — ia istilah undang-undang, dan tidak berubah mengikut bahasa pembaca.',

        'purchase_order_prefix' => 'Awalan pesanan belian',
        'purchase_return_prefix' => 'Awalan pemulangan belian',
        'sales_order_prefix' => 'Awalan pesanan jualan',
        'sales_return_prefix' => 'Awalan pemulangan jualan',
        'prefix_placeholder' => 'cth. PO',
        'prefix_hint' => 'Huruf, nombor dan sengkang. Pesanan belian dengan awalan “PO” dibaca sebagai PO-2026-0001.',

        'number_reset' => 'Mula semula penomboran',
        'number_reset_placeholder' => 'Pilih bila kiraan bermula semula',
        'number_reset_hint' => 'Mula semula setiap tahun meletakkan tahun dalam nombor dan mengira semula dari satu. Tidak sekali-kali bermakna kiraan berterusan, iaitu yang biasanya diperlukan oleh perniagaan yang berpindah daripada sistem lain.',

        'financial_year_start_month' => 'Tahun kewangan bermula pada',
        'financial_year_start_month_placeholder' => 'Pilih bulan',
        'financial_year_start_month_hint' => 'Hanya digunakan apabila penomboran bermula semula setiap tahun. Sesuatu tahun dilabel mengikut bulan ia bermula, jadi April 2025 hingga Mac 2026 dinomborkan sebagai 2025 sepanjang tempoh itu.',
    ],

    'number_reset' => [
        'yearly' => 'Setiap tahun kewangan',
        'never' => 'Tidak sekali-kali',
    ],

    'currency' => [
        'myr' => 'MYR — Ringgit Malaysia',
        'sgd' => 'SGD — Dolar Singapura',
        'usd' => 'USD — Dolar AS',
        'eur' => 'EUR — Euro',
        'cny' => 'CNY — Yuan China',
    ],

    'month' => [
        'january' => 'Januari',
        'february' => 'Februari',
        'march' => 'Mac',
        'april' => 'April',
        'may' => 'Mei',
        'june' => 'Jun',
        'july' => 'Julai',
        'august' => 'Ogos',
        'september' => 'September',
        'october' => 'Oktober',
        'november' => 'November',
        'december' => 'Disember',
    ],

    'validation' => [
        'prefix' => 'Awalan hanya boleh menggunakan huruf, nombor dan sengkang.',
    ],

    'action' => [
        'save' => 'Simpan tetapan',
        'saving' => 'Menyimpan…',
    ],

    'toast' => [
        'saved' => 'Tetapan perniagaan disimpan.',
    ],
];
