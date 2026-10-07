<?php

namespace Database\Seeders;

use App\Models\Dpl\CommercialParameter;
use App\Services\Dpl\DplPricing;
use Illuminate\Database\Seeder;

/**
 * Parameter komersial yang nilainya sudah dikonfirmasi user. Idempotent: tidak menimpa nilai yang sudah diubah admin.
 */
class CommercialParameterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PPN 11% untuk HNA+PPN di form DPL (user confirmation 2026-10-07).
        CommercialParameter::query()->firstOrCreate(
            ['code' => DplPricing::PPN_PARAMETER],
            [
                'value' => CommercialParameter::wrapValue(CommercialParameter::TYPE_NUMBER, '11'),
                'description' => 'Persentase PPN untuk HNA+PPN pada DPL',
                'is_active' => true,
            ],
        );
    }
}
