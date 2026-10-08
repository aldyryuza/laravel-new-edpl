<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Outlet;
use App\Models\OutletSalesAssignment;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel PIC outlet (AM / SPV, legacy dpl_map_outlet_users). Upsert berdasarkan outlet. All-or-nothing.
 * User dicari lewat NIK; kolom kosong = tanpa AM / SPV (minimal salah satu terisi).
 */
class OutletSalesAssignmentImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_outlet', 'nik_am', 'nik_spv'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, self::COLUMNS));

        $result = DB::transaction(function () use ($records): array {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $attributes) {
                $assignment = OutletSalesAssignment::query()->firstOrNew(['outlet_id' => $attributes['outlet_id']]);
                $isNew = ! $assignment->exists;
                $assignment->fill($attributes);

                if (! $isNew && ! $assignment->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $assignment->save();
                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'outlet_sales_assignment', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import PIC outlet dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode_outlet', 'Wajib, kode outlet terdaftar.'],
                ['nik_am', 'NIK user AM yang aktif. Boleh kosong bila nik_spv diisi.'],
                ['nik_spv', 'NIK user SPV yang aktif. Boleh kosong bila nik_am diisi.'],
                [null, null],
                ['Outlet yang sudah punya PIC akan diperbarui (kolom kosong = dikosongkan); outlet baru akan dibuat.', null],
                ['Pemilik DPL anak konsolidasi Group RS = SPV, bila kosong AM.', null],
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
        $niks = array_values(array_filter(array_merge(
            array_map(fn (array $row) => $this->text($row['nik_am'] ?? null), $rows),
            array_map(fn (array $row) => $this->text($row['nik_spv'] ?? null), $rows),
        )));
        $userIds = User::query()->whereIn('nik', $niks)->where('is_active', true)->pluck('id', 'nik')->all();
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $outletCode = $this->text($row['kode_outlet'] ?? null);
            $amNik = $this->text($row['nik_am'] ?? null);
            $spvNik = $this->text($row['nik_spv'] ?? null);
            $rowErrors = [];

            if ($outletCode === '' || ! isset($outletIds[$outletCode])) {
                $rowErrors[] = $outletCode === '' ? 'kode_outlet wajib diisi' : "kode_outlet {$outletCode} tidak ditemukan";
            }

            if ($amNik === '' && $spvNik === '') {
                $rowErrors[] = 'isi nik_am atau nik_spv';
            }

            foreach (['nik_am' => $amNik, 'nik_spv' => $spvNik] as $column => $nik) {
                if ($nik !== '' && ! isset($userIds[$nik])) {
                    $rowErrors[] = "{$column} {$nik} tidak ditemukan atau user nonaktif";
                }
            }

            if ($rowErrors === [] && isset($seen[$outletCode])) {
                $rowErrors[] = "kode_outlet {$outletCode} muncul lebih dari sekali di file";
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $seen[$outletCode] = true;
            $records[] = [
                'outlet_id' => $outletIds[$outletCode],
                'am_user_id' => $amNik === '' ? null : $userIds[$amNik],
                'spv_user_id' => $spvNik === '' ? null : $userIds[$spvNik],
            ];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
