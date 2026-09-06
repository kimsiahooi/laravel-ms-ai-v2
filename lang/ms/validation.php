<?php

declare(strict_types=1);

/**
 * Rujuk lang/en/validation.php untuk sebabnya fail ini hanya sebahagian daripada
 * fail rangka kerja.
 */

return [

    'array' => 'Ruangan :attribute mestilah senarai.',
    'boolean' => 'Ruangan :attribute mestilah benar atau palsu.',
    'decimal' => 'Ruangan :attribute mestilah mempunyai :decimal tempat perpuluhan.',
    'distinct' => 'Ruangan :attribute mempunyai nilai berulang.',
    'different' => ':attribute dan :other mestilah berbeza.',
    'email' => 'Ruangan :attribute mestilah alamat e-mel yang sah.',
    'enum' => ':attribute yang dipilih tidak sah.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'gt' => [
        'numeric' => 'Ruangan :attribute mestilah lebih besar daripada :value.',
    ],
    'gte' => [
        'numeric' => 'Ruangan :attribute mestilah lebih besar daripada atau sama dengan :value.',
    ],
    'image' => 'Ruangan :attribute mestilah imej.',
    'in' => ':attribute yang dipilih tidak sah.',
    'integer' => 'Ruangan :attribute mestilah integer.',
    'max' => [
        'array' => 'Ruangan :attribute tidak boleh mempunyai lebih daripada :max item.',
        'file' => 'Ruangan :attribute tidak boleh melebihi :max kilobait.',
        'numeric' => 'Ruangan :attribute tidak boleh melebihi :max.',
        'string' => 'Ruangan :attribute tidak boleh melebihi :max aksara.',
    ],
    'mimes' => 'Ruangan :attribute mestilah fail berjenis: :values.',
    'min' => [
        'string' => 'Ruangan :attribute mestilah sekurang-kurangnya :min aksara.',
    ],
    'numeric' => 'Ruangan :attribute mestilah nombor.',
    'regex' => 'Format ruangan :attribute tidak sah.',
    'required' => 'Ruangan :attribute diperlukan.',
    'string' => 'Ruangan :attribute mestilah rentetan aksara.',
    'unique' => ':attribute tersebut telah digunakan.',

    'attributes' => [
        'address' => 'alamat',
        'admin_email' => 'e-mel pentadbir',
        'admin_name' => 'nama pentadbir',
        'admin_password' => 'kata laluan pentadbir',
        'barcode' => 'kod bar',
        'category_id' => 'kategori',
        'city' => 'bandar',
        'code' => 'kod',
        'contact_person' => 'orang untuk dihubungi',
        'counted_quantity' => 'kuantiti dikira',
        'country_code' => 'negara',
        'description' => 'keterangan',
        'email' => 'e-mel',
        'image' => 'gambar',
        'item' => 'item',
        'items' => 'bahan',
        'items.*.quantity' => 'kuantiti',
        'items.*.raw_material_id' => 'bahan mentah',
        'line' => 'baris',
        'location_id' => 'tapak',
        'min_stock' => 'paras pesanan semula',
        'name' => 'nama',
        'notes' => 'nota',
        'phone' => 'telefon',
        'postcode' => 'poskod',
        'quantity' => 'kuantiti',
        'registration_no' => 'nombor pendaftaran',
        'remove_image' => 'buang gambar',
        'sku' => 'SKU',
        'slug' => 'alamat',
        'sst_registration_no' => 'nombor pendaftaran SST/GST',
        'state_code' => 'kod negeri',
        'supplier_id' => 'pembekal',
        'tax_id' => 'nombor cukai',
        'tin' => 'TIN',
        'type' => 'jenis',
        'unit' => 'unit',
        'warehouse_id' => 'gudang',
        'from_warehouse_id' => 'gudang sumber',
        'to_warehouse_id' => 'gudang destinasi',
    ],

];
