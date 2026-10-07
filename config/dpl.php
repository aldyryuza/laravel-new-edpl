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

];
