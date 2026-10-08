<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kode perusahaan pada nomor DPL
    |--------------------------------------------------------------------------
    |
    | Bagian tetap pada nomor DPL: {YYMM}-{company_code}-{kode zona}-{urut}, mis. 2610-SAI-E-0039.
    |
    */

    'company_code' => env('DPL_COMPANY_CODE', 'SAI'),

    /*
    |--------------------------------------------------------------------------
    | Zona waktu tanggal bisnis DPL
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk periode nomor DPL (YYMM) dan validasi "Date Start tidak boleh backdated",
    | supaya pergantian hari/bulan mengikuti waktu kantor, bukan UTC.
    |
    */

    'timezone' => env('DPL_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Jenis harga HNA item DPL
    |--------------------------------------------------------------------------
    |
    | Kode jenis harga => kolom product_prices yang dipakai sebagai HNA. Disimpan di
    | dpl_item_terms.price_type. Item baru memakai default_price_type, tapi bisa diganti per item.
    |
    */

    'price_types' => [
        'PRICE_BARU_2026' => 'price',
        'PRICE_LAMA_2025' => 'regular_price',
    ],

    'default_price_type' => 'PRICE_BARU_2026',

    /*
    | Jenis harga yang hanya boleh untuk volume product tertentu (ml). Jenis harga yang tidak
    | tercantum boleh untuk semua product.
    */

    'price_type_volumes' => [
        'PRICE_LAMA_2025' => [500],
    ],

    /*
    |--------------------------------------------------------------------------
    | Group product di form item DPL
    |--------------------------------------------------------------------------
    |
    | Urutan pilihan: group volume (label => volume ml, otomatis dari products.volume), lalu
    | group manual dari kolom products.product_group. Product di luar group tidak bisa dipilih di DPL.
    |
    */

    'volume_groups' => [
        '1L' => 1000,
        '500ml' => 500,
        '250ml' => 250,
        '100ml' => 100,
        '25ml' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reviewer "Open" item yang ditolak otomatis
    |--------------------------------------------------------------------------
    |
    | Nama role approver (approver_assignments) yang boleh membuka item auto reject (legacy: GM).
    |
    */

    'override_reviewer_role' => env('DPL_OVERRIDE_REVIEWER_ROLE', 'GM'),

    'product_groups' => [
        'IV CAT WING',
        'IV CAT PEN',
    ],

];
