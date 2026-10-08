<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\City;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel logistic fee (%) per kota. Hanya memperbarui kota yang sudah ada (kode tidak terdaftar = error baris);
 * logistic_fee kosong = tidak diubah. All-or-nothing.
 */
class CityLogisticFeeImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_kota', 'logistic_fee'];

    /**
     * @return array{updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, self::COLUMNS));

        $result = DB::transaction(function () use ($records): array {
            $result = ['updated' => 0, 'unchanged' => 0];
            $cities = City::query()->whereIn('code', $this->codes($records))->get()->keyBy('code');

            foreach ($records as $code => $logisticFee) {
                $city = $cities->get((string) $code);

                if ($logisticFee !== null) {
                    $city->logistic_fee = $logisticFee;
                }

                if (! $city->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $city->save();
                $result['updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'city_logistic_fee', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import logistic fee kota dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode_kota', 'Wajib, kode kota/kabupaten terdaftar (kode wilayah BPS, mis. 3216). Import hanya memperbarui kota yang sudah ada.'],
                ['logistic_fee', 'Opsional, logistic fee DPL (%), angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal). Kosong = tidak diubah.'],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, string|null> kode kota => logistic fee baru (null = tidak diubah)
     */
    private function validateRows(array $rows): array
    {
        $codes = array_values(array_filter(array_map(fn (array $row) => $this->text($row['kode_kota'] ?? null), $rows)));
        $existing = array_flip($this->codes(City::query()->whereIn('code', $codes)->pluck('id', 'code')->all()));
        $errors = [];
        $records = [];

        foreach ($rows as $index => $row) {
            $code = $this->text($row['kode_kota'] ?? null);
            $rowErrors = [];

            if ($code === '') {
                $rowErrors[] = 'kode_kota wajib diisi';
            } elseif (! isset($existing[$code])) {
                $rowErrors[] = "kode_kota {$code} tidak ditemukan";
            } elseif (array_key_exists($code, $records)) {
                $rowErrors[] = "kode_kota {$code} muncul lebih dari sekali di file";
            }

            $logisticFee = $this->percent($row['logistic_fee'] ?? null, 'logistic_fee', false, $rowErrors);

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $records[$code] = $logisticFee;
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
