<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Dpl\ProductPriceOverride;
use App\Models\OutletGroup;
use App\Models\Product;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel harga khusus (JKN/PADMA, dll.). Upsert berdasarkan (jenis harga, group RS, product, berlaku mulai):
 * periode baru menjadi baris baru sehingga histori tersimpan. All-or-nothing.
 */
class ProductPriceOverrideImportService extends ExcelImporter
{
    public const COLUMNS = ['jenis_harga', 'kode_grup', 'kode_product', 'harga', 'berlaku_mulai', 'berlaku_sampai', 'aktif', 'catatan'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['jenis_harga', 'kode_product', 'harga']));

        $result = DB::transaction(function () use ($records): array {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $attributes) {
                $override = ProductPriceOverride::query()
                    ->where('price_type', $attributes['price_type'])
                    ->where('product_id', $attributes['product_id'])
                    ->when(
                        $attributes['outlet_group_id'] === null,
                        fn ($query) => $query->whereNull('outlet_group_id'),
                        fn ($query) => $query->where('outlet_group_id', $attributes['outlet_group_id']),
                    )
                    ->when(
                        $attributes['valid_from'] === null,
                        fn ($query) => $query->whereNull('valid_from'),
                        fn ($query) => $query->whereDate('valid_from', $attributes['valid_from']),
                    )
                    ->first() ?? new ProductPriceOverride;
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
            ->withProperties([...AuditLog::requestContext(), 'type' => 'product_price_override', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import harga khusus dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        $guide = [
            ['jenis_harga', 'Wajib, maks. 50 karakter, mis. JKN atau PADMA (disimpan huruf besar).'],
            ['kode_grup', 'Opsional, kode group RS (lihat daftar di bawah). Kosong = berlaku tanpa group tertentu.'],
            ['kode_product', 'Wajib, kode product terdaftar.'],
            ['harga', 'Wajib, angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal).'],
            ['berlaku_mulai', 'Opsional, format YYYY-MM-DD. Jenis + group + product + berlaku mulai yang sama akan diperbarui.'],
            ['berlaku_sampai', 'Opsional, format YYYY-MM-DD, tidak boleh sebelum berlaku_mulai.'],
            ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
            ['catatan', 'Opsional.'],
            [null, null],
            ['Kode Group RS', 'Nama Group RS'],
        ];

        foreach (OutletGroup::query()->whereNotNull('code')->orderBy('code')->get(['code', 'name']) as $group) {
            $guide[] = [$group->code, $group->name];
        }

        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], $guide),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function validateRows(array $rows): array
    {
        $productIds = Product::query()->pluck('id', 'code')->all();
        $groupIds = OutletGroup::query()->whereNotNull('code')->pluck('id', 'code')->all();
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $priceType = mb_strtoupper($this->text($row['jenis_harga'] ?? null));
            $groupCode = $this->text($row['kode_grup'] ?? null);
            $productCode = $this->text($row['kode_product'] ?? null);
            $note = $this->text($row['catatan'] ?? null);
            $rowErrors = [];

            if ($priceType === '') {
                $rowErrors[] = 'jenis_harga wajib diisi';
            } elseif (mb_strlen($priceType) > 50) {
                $rowErrors[] = 'jenis_harga maksimal 50 karakter';
            }

            if ($groupCode !== '' && ! isset($groupIds[$groupCode])) {
                $rowErrors[] = "kode_grup {$groupCode} tidak ditemukan";
            }

            if ($productCode === '' || ! isset($productIds[$productCode])) {
                $rowErrors[] = $productCode === '' ? 'kode_product wajib diisi' : "kode_product {$productCode} tidak ditemukan";
            }

            $price = $this->amount($row['harga'] ?? null, 'harga', true, $rowErrors);
            $validFrom = $this->date($row['berlaku_mulai'] ?? null, 'berlaku_mulai', $rowErrors);
            $validUntil = $this->date($row['berlaku_sampai'] ?? null, 'berlaku_sampai', $rowErrors);
            $active = $this->boolean($row['aktif'] ?? null);

            if ($validFrom !== null && $validUntil !== null && $validUntil < $validFrom) {
                $rowErrors[] = 'berlaku_sampai tidak boleh sebelum berlaku_mulai';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            $key = "{$priceType}|{$groupCode}|{$productCode}|{$validFrom}";

            if ($rowErrors === [] && isset($seen[$key])) {
                $rowErrors[] = 'kombinasi jenis harga, group, product, dan berlaku_mulai muncul lebih dari sekali di file';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $seen[$key] = true;
            $records[] = [
                'price_type' => $priceType,
                'outlet_group_id' => $groupCode === '' ? null : $groupIds[$groupCode],
                'product_id' => $productIds[$productCode],
                'price' => $price,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_active' => $active,
                'note' => $note === '' ? null : $note,
            ];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }
}
