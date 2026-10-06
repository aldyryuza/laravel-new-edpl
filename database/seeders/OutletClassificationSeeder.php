<?php

namespace Database\Seeders;

use App\Models\OutletCategory;
use App\Models\OwnershipType;
use Illuminate\Database\Seeder;

/**
 * Klasifikasi outlet: kategori (legacy GRUP1) dan kepemilikan (legacy GRUP2).
 * Kode sementara; sesuaikan dengan hasil mapping data legacy. Idempotent berdasarkan code.
 */
class OutletClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['RUMAH_SAKIT' => 'Rumah Sakit', 'INSTANSI' => 'Instansi'] as $code => $name) {
            OutletCategory::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (['PEMERINTAH' => 'Pemerintah', 'NON_PEMERINTAH' => 'Bukan Pemerintah'] as $code => $name) {
            OwnershipType::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
