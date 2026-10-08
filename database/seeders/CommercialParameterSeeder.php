<?php

namespace Database\Seeders;

use App\Models\Dpl\CommercialParameter;
use App\Services\Dpl\DplCalculator;
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

        // Batas Total Diskon kalkulator per team (legacy FunctionHelper: Tim A 30, Tim B 45).
        foreach (['A' => '30', 'B' => '45'] as $team => $limit) {
            CommercialParameter::query()->firstOrCreate(
                ['code' => DplCalculator::LIMIT_PARAMETER_PREFIX.$team],
                [
                    'value' => CommercialParameter::wrapValue(CommercialParameter::TYPE_NUMBER, $limit),
                    'description' => "Batas Total Diskon kalkulator DPL Tim {$team} (%); item di atas batas ditolak otomatis",
                    'is_active' => true,
                ],
            );
        }
    }
}
