<?php

namespace App\Services\Dpl;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Nomor DPL: {YYMM}-{kode perusahaan}-{kode zona}-{urut 4 digit}, mis. 2610-SAI-E-0039.
 * Urutan terpisah per zona dan mulai lagi dari 0001 setiap bulan. Nomor dibuat saat draft dibuat,
 * sehingga draft yang dihapus meninggalkan lubang pada urutan.
 */
class DplNumberGenerator
{
    /**
     * Ambil nomor berikutnya. Panggil di dalam transaksi pembuatan draft agar counter ikut batal bila gagal.
     */
    public function next(string $zoneCode, CarbonInterface $date): string
    {
        $period = $date->format('ym');

        // Atomik: baris counter dikunci oleh INSERT ... ON CONFLICT sampai transaksi selesai.
        $number = DB::selectOne(
            'INSERT INTO dpl.dpl_number_sequences (period, zone_code, last_number, created_at, updated_at)
             VALUES (?, ?, 1, now(), now())
             ON CONFLICT (period, zone_code)
             DO UPDATE SET last_number = dpl.dpl_number_sequences.last_number + 1, updated_at = now()
             RETURNING last_number',
            [$period, $zoneCode],
        )->last_number;

        return sprintf('%s-%s-%s-%04d', $period, config('dpl.company_code'), $zoneCode, $number);
    }
}
