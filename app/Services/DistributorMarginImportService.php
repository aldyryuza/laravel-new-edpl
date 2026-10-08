<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Distributor;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel margin dasar DPL distributor (margin, margin Tim B, punya margin khusus per outlet).
 * Hanya memperbarui distributor yang sudah ada (kode tidak terdaftar = error baris); sel kosong = nilai tidak diubah.
 * All-or-nothing.
 */
class DistributorMarginImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_distributor', 'margin', 'margin_tim_b', 'margin_khusus_outlet'];

    /**
     * @return array{updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode_distributor']));

        $result = DB::transaction(function () use ($records): array {
            $result = ['updated' => 0, 'unchanged' => 0];
            $distributors = Distributor::query()->whereIn('code', $this->codes($records))->get()->keyBy('code');

            foreach ($records as $code => $attributes) {
                $distributor = $distributors->get((string) $code);
                $distributor->fill($attributes);

                if (! $distributor->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $distributor->save();
                $result['updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'distributor_margin', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import margin distributor dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode_distributor', 'Wajib, kode distributor terdaftar. Import hanya memperbarui distributor yang sudah ada.'],
                ['margin', 'Opsional, margin DPL (%), angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal). Kosong = tidak diubah.'],
                ['margin_tim_b', 'Opsional, margin DPL Tim B (%), angka >= 0, maks. 4 desimal. Kosong = tidak diubah.'],
                ['margin_khusus_outlet', 'Opsional, punya margin khusus per outlet: 1/ya atau 0/tidak. Kosong = tidak diubah.'],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>> kode distributor => atribut yang diubah
     */
    private function validateRows(array $rows): array
    {
        $codes = array_values(array_filter(array_map(fn (array $row) => $this->text($row['kode_distributor'] ?? null), $rows)));
        $existing = array_flip($this->codes(Distributor::query()->whereIn('code', $codes)->pluck('id', 'code')->all()));
        $errors = [];
        $records = [];

        foreach ($rows as $index => $row) {
            $code = $this->text($row['kode_distributor'] ?? null);
            $rowErrors = [];
            $attributes = [];

            if ($code === '') {
                $rowErrors[] = 'kode_distributor wajib diisi';
            } elseif (! isset($existing[$code])) {
                $rowErrors[] = "kode_distributor {$code} tidak ditemukan";
            } elseif (isset($records[$code])) {
                $rowErrors[] = "kode_distributor {$code} muncul lebih dari sekali di file";
            }

            foreach (['margin' => 'margin', 'margin_tim_b' => 'margin_team_b'] as $column => $attribute) {
                $value = $this->percent($row[$column] ?? null, $column, false, $rowErrors);

                if ($value !== null) {
                    $attributes[$attribute] = $value;
                }
            }

            if ($this->text($row['margin_khusus_outlet'] ?? null) !== '') {
                $isSpecialCase = $this->boolean($row['margin_khusus_outlet']);

                if ($isSpecialCase === null) {
                    $rowErrors[] = 'margin_khusus_outlet harus 1/ya atau 0/tidak';
                } else {
                    $attributes['is_special_case_margin'] = $isSpecialCase;
                }
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $records[$code] = $attributes;
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
