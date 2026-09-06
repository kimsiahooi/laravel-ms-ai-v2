<?php

declare(strict_types=1);

/*
| Produk — apa yang ruang kerja ini jual. Kroma yang dikongsi dengan senarai lain
| (carian, penomboran, Batal, Edit, Padam) berada dalam common.php; nama unit
| berada dalam units.php.
*/

return [
    'title' => 'Produk',
    'subtitle' => 'Apa yang anda jual, dan cara ia difailkan.',

    'search_placeholder' => 'Cari nama, SKU atau kod bar…',

    'filter' => [
        'material' => 'Diperbuat daripada',
        'all_materials' => 'Mana-mana bahan',
        'materials_selected' => 'Mana-mana daripada :count bahan',
        'material_hint' => '[0,1] Tandakan lebih daripada satu untuk meluaskan carian — produk hanya perlukan satu daripadanya.|[2,*] Menunjukkan produk yang menggunakan mana-mana daripada :count bahan ini, bukan produk yang menggunakan kesemuanya.',
        'material_search' => 'Cari bahan…',
        'material_empty' => 'Tiada bahan sepadan.',
        'unit' => 'Unit',
        'all_units' => 'Semua unit',
    ],

    'column' => [
        'name' => 'Produk',
        'sku' => 'SKU',
        'default_price' => 'Harga lalai',
        'category' => 'Kategori',
        'supplier' => 'Pembekal',
        'created' => 'Ditambah',
        'creator' => 'Ditambah oleh',
        'view_category' => 'Lihat :name dalam kategori',
        'view_supplier' => 'Lihat :name dalam pembekal',
    ],

    'empty' => [
        'title' => 'Belum ada produk',
        'description' => 'Tambah yang pertama dan ia akan sedia untuk dijual, dikira dan dibina daripada bahan mentah anda.',
    ],

    'no_match' => [
        'title' => 'Tiada produk sepadan',
        'description' => 'Tiada apa-apa di sini sepadan dengan “:term”.',
    ],

    'create' => [
        'trigger' => 'Produk baharu',
        'title' => 'Produk baharu',
        'description' => 'Satu kod untuk merujuknya, dan unit yang anda gunakan untuk menjualnya.',
        'submit' => 'Cipta produk',
        'submitting' => 'Mencipta…',
    ],

    'edit' => [
        'title' => 'Edit produk',
        'description' => 'Perubahan digunakan di semua tempat produk ini digunakan.',
        'submit' => 'Simpan perubahan',
        'submitting' => 'Menyimpan…',
    ],

    'group' => [
        'identity' => 'Apa produk ini',
        'filing' => 'Cara ia difailkan',
        'filing_hint' => 'Kedua-duanya pilihan — ia mengumpulkan produk dalam senarai dan laporan, dan boleh ditetapkan kemudian.',
    ],

    'field' => [
        'name' => 'Nama',
        'name_placeholder' => 'cth. Bangku lipat',
        'sku' => 'SKU',
        'sku_placeholder' => 'cth. P-001',
        'sku_hint' => 'Kod anda sendiri untuk produk ini. Ia muncul pada pesanan jualan dan invois, dan tiada dua produk boleh berkongsi kod yang sama.',
        'barcode' => 'Kod bar',
        'barcode_placeholder' => 'Imbas atau taip kod bar',
        'barcode_hint' => 'Diimbas untuk mencari produk ini semasa kiraan stok, pergerakan dan pemindahan.',
        'unit' => 'Unit',
        'unit_placeholder' => 'Pilih unit',
        'unit_hint' => 'Unit yang anda gunakan untuk menjualnya. Setiap kuantiti yang direkodkan untuk produk ini ialah bilangan unit ini.',
        'default_price' => 'Harga lalai',
        'default_price_placeholder' => 'cth. 49.90',
        'default_price_hint' => 'Harga jualan biasa anda bagi satu unit, dalam :currency. Baris pesanan jualan akan bermula daripada angka ini dan masih boleh diubah, jadi mengubahnya tidak menjejaskan pesanan yang telah dibuat.',
        'default_price_none' => 'Belum ditetapkan',
        'description' => 'Keterangan',
        'description_placeholder' => 'Apa produk ini, dalam satu atau dua baris',
        'image' => 'Gambar',
        'image_hint' => 'JPG, PNG atau WebP, sehingga 2 MB. Ia dipaparkan di sebelah produk dalam setiap senarai.',
        'image_remove' => 'Buang gambar',
        'image_alt' => 'Gambar produk',
        'category' => 'Kategori',
        'category_placeholder' => 'Pilih kategori',
        'category_search' => 'Cari kategori…',
        'category_empty' => 'Tiada kategori sepadan.',
        'supplier' => 'Pembekal',
        'supplier_placeholder' => 'Pilih pembekal',
        'supplier_search' => 'Cari pembekal…',
        'supplier_empty' => 'Tiada pembekal sepadan.',
    ],

    'bom' => [
        'action' => 'Senarai bahan',
        'title' => 'Senarai bahan',
        'description' => 'Bahan mentah yang digunakan untuk :name, dan berapa banyak diperlukan untuk membuat satu unit.',
        'submit' => 'Simpan senarai',
        'submitting' => 'Menyimpan…',
        'add' => 'Tambah bahan',
        'line' => 'Bahan :number',
        'column_material' => 'Bahan',
        'column_quantity' => 'Kuantiti seunit',
        'material_placeholder' => 'Pilih bahan',
        'material_search' => 'Cari bahan…',
        'material_empty' => 'Tiada bahan sepadan.',
        'quantity_placeholder' => 'cth. 0.35',
        'remove' => 'Buang bahan :number',
        'empty' => 'Belum ada bahan. Tambah yang pertama untuk menerangkan produk ini diperbuat daripada apa.',
        'none_available' => 'Belum ada bahan mentah dalam ruang kerja ini. Tambah satu dahulu dan ia akan tersedia di sini.',
        'count' => '{0} Tiada senarai|[1,*] :count bahan',
    ],

    'confirm' => [
        'delete_title' => 'Padam :name?',
        'delete_description' => 'Pesanan yang sudah dibuat untuk produk ini mengekalkan rekodnya — anda cuma tidak lagi boleh memilihnya untuk pesanan baharu.',
        'delete_submit' => 'Padam produk',
        'delete_submitting' => 'Memadam…',
    ],

    'toast' => [
        'bom_saved' => 'Senarai bahan untuk :name disimpan.',
        'created' => ':name telah dicipta.',
        'updated' => ':name telah dikemas kini.',
        'deleted' => ':name telah dipadam.',
    ],
];
