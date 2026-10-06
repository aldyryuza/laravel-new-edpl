<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Membaca baris sheet sebagai array dengan key = judul kolom (di-slug, mis. "Kode Region" -> kode_region).
 * Validasi dan penyimpanan dilakukan oleh service pemanggil.
 */
class SheetRowsImport implements Import, SkipsEmptyRows, WithHeadingRow {}
