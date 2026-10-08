<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Outlet;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel margin khusus outlet per grup distributor. Upsert berdasarkan (grup, outlet). All-or-nothing.
 */
class DistributorMarginOverrideImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_grup', 'kode_outlet', 'margin', 'catatan', 'aktif'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode_grup', 'kode_outlet', 'margin']));

        $result = DB::transaction(function () use ($records): array {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $attributes) {
                $override = DistributorMarginOverride::query()
                    ->where('distributor_group_code', $attributes['distributor_group_code'])
                    ->where('outlet_id', $attributes['outlet_id'])
                    ->first() ?? new DistributorMarginOverride;
                $isNew = ! $override->exists;
                $override->fill($attributes);

                if (! $isNew && ! $override->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $override->save();
                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'distributor_margin_override', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import margin khusus outlet dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode_grup', 'Wajib, kode grup distributor (grup_distributor di master Distributor), maks. 50 karakter. Disimpan huruf besar.'],
                ['kode_outlet', 'Wajib, kode outlet terdaftar.'],
                ['margin', 'Wajib, margin (%), angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal).'],
                ['catatan', 'Opsional, maks. 2000 karakter.'],
                ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
                [null, null],
                ['Grup + outlet yang sudah ada akan diperbarui; kombinasi baru akan dibuat.', null],
                ['Margin berlaku untuk cabang grup distributor yang ditandai "Punya margin khusus per outlet" di master Distributor.', null],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function validateRows(array $rows): array
    {
        $outletCodes = array_values(array_filter(array_map(fn (array $row) => $this->text($row['kode_outlet'] ?? null), $rows)));
        $outletIds = Outlet::query()->whereIn('code', $outletCodes)->pluck('id', 'code')->all();
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $groupCode = mb_strtoupper($this->text($row['kode_grup'] ?? null));
            $outletCode = $this->text($row['kode_outlet'] ?? null);
            $note = $this->text($row['catatan'] ?? null);
            $active = $this->boolean($row['aktif'] ?? null);
            $rowErrors = [];

            if ($groupCode === '') {
                $rowErrors[] = 'kode_grup wajib diisi';
            } elseif (mb_strlen($groupCode) > 50) {
                $rowErrors[] = 'kode_grup maksimal 50 karakter';
            }

            if ($outletCode === '' || ! isset($outletIds[$outletCode])) {
                $rowErrors[] = $outletCode === '' ? 'kode_outlet wajib diisi' : "kode_outlet {$outletCode} tidak ditemukan";
            }

            $margin = $this->percent($row['margin'] ?? null, 'margin', true, $rowErrors);

            if (mb_strlen($note) > 2000) {
                $rowErrors[] = 'catatan maksimal 2000 karakter';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            $key = "{$groupCode}|{$outletCode}";

            if ($rowErrors === [] && isset($seen[$key])) {
                $rowErrors[] = 'kombinasi kode_grup dan kode_outlet muncul lebih dari sekali di file';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $seen[$key] = true;
            $records[] = [
                'distributor_group_code' => $groupCode,
                'outlet_id' => $outletIds[$outletCode],
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
