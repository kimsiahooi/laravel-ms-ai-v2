<?php

declare(strict_types=1);

return [
    'actions' => [
        'cancel' => 'Batal',
        'clear_search' => 'Kosongkan carian',
        'close' => 'Tutup',
        'delete' => 'Padam',
        'edit' => 'Edit',
        'row_actions' => 'Tindakan untuk :name',
        'toggle_sidebar' => 'Togol bar sisi',
    ],

    // Marks a field the form will accept empty. Sits beside the label rather than
    // inside it, so the label stays the thing a screen reader announces.
    'field' => [
        'none' => 'Tidak ditetapkan',
        'optional' => '(pilihan)',
    ],

    'confirm' => [
        'type_to_confirm' => 'Taip :phrase untuk mengesahkan',
    ],

    'password' => [
        'hide' => 'Sembunyikan kata laluan',
        'show' => 'Papar kata laluan',
    ],

    'errors' => [
        'generic' => 'Sesuatu tidak kena.',
    ],

    'filter' => [
        'trigger' => 'Penapis',
        'description' => 'Tapiskan senarai kepada apa yang anda cari.',
        'clear' => 'Kosongkan',
        'clear_all' => 'Kosongkan semua penapis',
    ],

    'columns' => [
        'trigger' => 'Lajur',
        'description' => 'Pilih apa yang disenaraikan, dan seret untuk menyusun semula.',
        'reset' => 'Kembali kepada asal',
        'hidden_count' => '{1} 1 disembunyikan|[2,*] :count disembunyikan',
        'move_up' => 'Alihkan :column ke hadapan',
        'move_down' => 'Alihkan :column ke belakang',
        'drag' => 'Seret untuk menyusun :column',
        'narrow_hidden' => 'Turut disembunyikan pada skrin sempit',
        'sorted_hint' => 'Senarai ini disusun mengikut lajur ini',
        'last_hint' => 'Sekurang-kurangnya satu lajur perlu kekal',
        'save_failed' => 'Tidak dapat menyimpan pilihan lajur anda. Ia akan kembali seperti biasa apabila anda muat semula.',
    ],

    'list' => [
        'no_matches' => 'Tiada padanan',
        'no_matches_filtered' => 'Tiada apa-apa di sini sepadan dengan penapis yang anda gunakan.',
        'no_matches_hint' => 'Tiada apa-apa sepadan dengan “:search”.',
        'page_empty' => 'Tiada apa-apa pada halaman ini',
        'page_empty_hint' => 'Baris itu sudah tiada — senarai mungkin menjadi lebih pendek sejak halaman ini dibuka.',
        'back_to_first' => 'Kembali ke halaman pertama',
        'actions_column' => 'Tindakan',
        'rows_per_page' => 'Baris setiap halaman',
    ],

    'pagination' => [
        'label' => 'Penomboran halaman',
        'page' => 'Halaman :page',
        'no_results' => 'Tiada keputusan',
        'showing' => 'Memaparkan :from–:to daripada :total',
        'page_of' => 'Halaman :current daripada :last',
        'previous' => 'Sebelumnya',
        'next' => 'Seterusnya',
    ],

    'language' => [
        'change' => 'Tukar bahasa',
    ],

    'theme' => [
        'change' => 'Tukar tema',
        'light' => 'Cerah',
        'dark' => 'Gelap',
        'system' => 'Sistem',
    ],

    'time' => [
        'just_now' => 'sebentar tadi',
        'minutes_ago' => ':count min lalu',
        'hours_ago' => ':count jam lalu',
        'days_ago' => ':count hari lalu',
    ],
];
