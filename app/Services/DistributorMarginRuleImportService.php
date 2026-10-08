<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\City;
use App\Models\Dpl\DistributorMarginRule;
use App\Models\Product;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel margin khusus product per grup distributor. Upsert berdasarkan (grup, product, kota);
 * kode_kota kosong = semua kota. All-or-nothing.
 */
class DistributorMarginRuleImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_grup', 'kode_product', 'kode_kota', 'margin', 'catatan', 'aktif'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode_grup', 'kode_product', 'margin']));

        $result = DB::transaction(function () use ($records): array {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $attributes) {
                $rule = DistributorMarginRule::query()
                    ->where('distributor_group_code', $attributes['distributor_group_code'])
                    ->where('product_id', $attributes['product_id'])
                    ->when(
                        $attributes['city_id'] === null,
                        fn ($query) => $query->whereNull('city_id'),
                        fn ($query) => $query->where('city_id', $attributes['city_id']),
                    )
                    ->first() ?? new DistributorMarginRule;
                $isNew = ! $rule->exists;
                $rule->fill($attributes);

                if (! $isNew && ! $rule->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $rule->save();
                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'distributor_margin_rule', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import margin khusus product dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode_grup', 'Wajib, kode grup distributor (grup_distributor di master Distributor), maks. 50 karakter. Disimpan huruf besar.'],
                ['kode_product', 'Wajib, kode product terdaftar.'],
                ['kode_kota', 'Opsional, kode kota/kabupaten terdaftar (kode wilayah BPS, mis. 3216). Kosong = semua kota.'],
                ['margin', 'Wajib, margin (%), angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal).'],
                ['catatan', 'Opsional, maks. 2000 karakter.'],
                ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
                [null, null],
                ['Grup + product + kota yang sudah ada akan diperbarui; kombinasi baru akan dibuat.', null],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function validateRows(array $rows): array
    {
        $productIds = Product::query()->pluck('id', 'code')->all();
        $cityIds = City::query()->pluck('id', 'code')->all();
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $groupCode = mb_strtoupper($this->text($row['kode_grup'] ?? null));
            $productCode = $this->text($row['kode_product'] ?? null);
            $cityCode = $this->text($row['kode_kota'] ?? null);
            $note = $this->text($row['catatan'] ?? null);
            $active = $this->boolean($row['aktif'] ?? null);
            $rowErrors = [];

            if ($groupCode === '') {
                $rowErrors[] = 'kode_grup wajib diisi';
            } elseif (mb_strlen($groupCode) > 50) {
                $rowErrors[] = 'kode_grup maksimal 50 karakter';
            }

            if ($productCode === '' || ! isset($productIds[$productCode])) {
                $rowErrors[] = $productCode === '' ? 'kode_product wajib diisi' : "kode_product {$productCode} tidak ditemukan";
            }

            if ($cityCode !== '' && ! isset($cityIds[$cityCode])) {
                $rowErrors[] = "kode_kota {$cityCode} tidak ditemukan";
            }

            $margin = $this->percent($row['margin'] ?? null, 'margin', true, $rowErrors);

            if (mb_strlen($note) > 2000) {
                $rowErrors[] = 'catatan maksimal 2000 karakter';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            $key = "{$groupCode}|{$productCode}|{$cityCode}";

            if ($rowErrors === [] && isset($seen[$key])) {
                $rowErrors[] = 'kombinasi kode_grup, kode_product, dan kode_kota muncul lebih dari sekali di file';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $seen[$key] = true;
            $records[] = [
                'distributor_group_code' => $groupCode,
                'product_id' => $productIds[$productCode],
                'city_id' => $cityCode === '' ? null : $cityIds[$cityCode],
                'margin' => $margin,
                'note' => $note === '' ? null : $note,
                'is_active' => $active,
            ];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
