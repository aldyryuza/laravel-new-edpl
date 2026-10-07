<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Product;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel master product: upsert berdasarkan kode, all-or-nothing.
 */
class ProductImportService extends ExcelImporter
{
    public const COLUMNS = ['kode', 'nama', 'volume', 'group_product', 'branded', 'netsuite_id', 'aktif'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode', 'nama']));

        $result = DB::transaction(function () use ($records): array {
            $existing = Product::query()->whereIn('code', $this->codes($records))->get()->keyBy('code');
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $code => $attributes) {
                $product = $existing->get((string) $code) ?? new Product;
                $isNew = ! $product->exists;
                $product->fill($attributes);

                if (! $isNew && ! $product->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $product->save();
                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'product', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import product dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], [
                ['kode', 'Wajib, maks. 20 karakter (legacy PID). Kode yang sudah ada akan diperbarui.'],
                ['nama', 'Wajib, maks. 255 karakter (legacy PNAME).'],
                ['volume', 'Opsional, angka dalam ml (mis. 500, 250, 100, 25), maks. 4 desimal.'],
                ['group_product', 'Opsional, salah satu: '.implode(', ', config('dpl.product_groups')).'. Kosong = ikut volume.'],
                ['branded', 'Opsional: 1/ya atau 0/tidak. Kosong = tidak.'],
                ['netsuite_id', 'Opsional, ID item di NetSuite.'],
                ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function validateRows(array $rows): array
    {
        $errors = [];
        $records = [];

        foreach ($rows as $index => $row) {
            $code = $this->text($row['kode'] ?? null);
            $name = $this->text($row['nama'] ?? null);
            $volume = $this->text($row['volume'] ?? null);
            $group = mb_strtoupper($this->text($row['group_product'] ?? null));
            $netsuiteId = $this->text($row['netsuite_id'] ?? null);
            $branded = $this->boolean($row['branded'] ?? null, default: false);
            $active = $this->boolean($row['aktif'] ?? null);
            $rowErrors = [];

            if ($code === '') {
                $rowErrors[] = 'kode wajib diisi';
            } elseif (mb_strlen($code) > 20) {
                $rowErrors[] = 'kode maksimal 20 karakter';
            } elseif (isset($records[$code])) {
                $rowErrors[] = "kode {$code} muncul lebih dari sekali di file";
            }

            if ($name === '') {
                $rowErrors[] = 'nama wajib diisi';
            } elseif (mb_strlen($name) > 255) {
                $rowErrors[] = 'nama maksimal 255 karakter';
            }

            if ($volume !== '' && (! is_numeric($volume) || $volume < 0 || $volume > 999999.9999)) {
                $rowErrors[] = 'volume harus angka 0 s/d 999999.9999';
            }

            if ($group !== '' && ! in_array($group, config('dpl.product_groups'), true)) {
                $rowErrors[] = 'group_product harus salah satu: '.implode(', ', config('dpl.product_groups'));
            }

            if (mb_strlen($netsuiteId) > 255) {
                $rowErrors[] = 'netsuite_id maksimal 255 karakter';
            }

            if ($branded === null) {
                $rowErrors[] = 'branded harus 1/ya atau 0/tidak';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $records[$code] = [
                'code' => $code,
                'name' => $name,
                'volume' => $volume === '' ? null : round((float) $volume, 4),
                'product_group' => $group === '' ? null : $group,
                'is_branded' => $branded,
                'netsuite_id' => $netsuiteId === '' ? null : $netsuiteId,
                'is_active' => $active,
            ];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
