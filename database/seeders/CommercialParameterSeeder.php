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

        // Aturan margin & logistic fee item DPL (legacy addDplItem.js / FunctionHelper; user confirmation 2026-10-08).
        $itemDefaults = [
            'KFTD_DISTRIBUTOR_GROUP' => ['text', 'KFTD', 'Kode grup distributor KFTD (margin Direct khusus)'],
            'INSTANSI_CATEGORY_CODE' => ['text', 'INSTANSI', 'Kode kategori outlet Instansi/Dinkes'],
            'DIRECT_MARGIN_KFTD_INSTANSI' => ['number', '9', 'Margin Direct distributor KFTD ke outlet Instansi (%)'],
            'DIRECT_MARGIN_KFTD_OTHER' => ['number', '11', 'Margin Direct distributor KFTD ke outlet lain (%)'],
            'DIRECT_MARGIN_INSTANSI_JAVA' => ['number', '3', 'Margin Direct distributor non-KFTD ke outlet Instansi, provinsi Jawa (%)'],
            'DIRECT_MARGIN_INSTANSI_OUTSIDE_JAVA' => ['number', '5', 'Margin Direct distributor non-KFTD ke outlet Instansi, luar Jawa (%)'],
            'OUTLET_MARGIN_EXCLUDED_TEAMS' => ['text', 'B', 'Team yang tidak memakai margin khusus outlet (pisahkan koma)'],
            'SMALL_VOLUME_ML' => ['number', '25', 'Volume product (ml) dengan aturan khusus: tanpa margin khusus outlet, logistic fee dibagi'],
            'NATIONAL_DISTRIBUTOR_GROUPS' => ['text', 'KFTD,UDC,SDL,PPI', 'Grup distributor nasional (pisahkan koma)'],
            'NATIONAL_DISTRIBUTOR_LOGISTIC_FEE' => ['number', '6', 'Logistic fee distributor nasional (%)'],
            'SPECIAL_LOGISTIC_DIVISOR' => ['number', '3', 'Pembagi logistic fee product volume kecil & produk tim B'],
            'DEFAULT_LOGISTIC_CITY_CODE' => ['text', '3216', 'Kode kota logistic fee bila kota distributor kosong'],
        ];

        foreach ($itemDefaults as $code => [$type, $value, $description]) {
            CommercialParameter::query()->firstOrCreate(
                ['code' => $code],
                ['value' => CommercialParameter::wrapValue($type, $value), 'description' => $description, 'is_active' => true],
            );
        }

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
