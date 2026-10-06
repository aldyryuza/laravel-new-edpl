<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Dpl\PriceRegion;
use App\Models\Dpl\ProductPrice;
use App\Models\Product;
use App\Support\AuditLog;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Import Excel harga product per region harga. Upsert berdasarkan (product, region harga, berlaku mulai):
 * harga periode baru menjadi baris baru sehingga histori harga tetap tersimpan. All-or-nothing.
 */
class ProductPriceImportService extends ExcelImporter
{
    public const COLUMNS = ['kode_product', 'kode_region_harga', 'harga', 'harga_reguler', 'berlaku_mulai', 'berlaku_sampai', 'aktif'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode_product', 'kode_region_harga', 'harga']));

        $result = DB::transaction(function () use ($records): array {
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($records as $attributes) {
                $price = ProductPrice::query()
                    ->where('product_id', $attributes['product_id'])
                    ->where('price_region_id', $attributes['price_region_id'])
                    ->when(
                        $attributes['valid_from'] === null,
                        fn ($query) => $query->whereNull('valid_from'),
                        fn ($query) => $query->whereDate('valid_from', $attributes['valid_from']),
                    )
                    ->first() ?? new ProductPrice;
                $isNew = ! $price->exists;
                $price->fill($attributes);

                if (! $isNew && ! $price->isDirty()) {
                    $result['unchanged']++;

                    continue;
                }

                $price->save();
                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'product_price', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import harga product dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        $guide = [
            ['kode_product', 'Wajib, kode product terdaftar.'],
            ['kode_region_harga', 'Wajib, kode region harga (lihat daftar di bawah).'],
            ['harga', 'Wajib, angka >= 0, maks. 4 desimal (titik sebagai pemisah desimal).'],
            ['harga_reguler', 'Opsional, angka >= 0.'],
            ['berlaku_mulai', 'Opsional, format YYYY-MM-DD. Product + region + berlaku mulai yang sama akan diperbarui; tanggal berbeda menjadi baris harga baru.'],
            ['berlaku_sampai', 'Opsional, format YYYY-MM-DD, tidak boleh sebelum berlaku_mulai.'],
            ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
            [null, null],
            ['Kode Region Harga', 'Nama Region Harga'],
        ];

        foreach (PriceRegion::query()->orderBy('code')->get(['code', 'name']) as $region) {
            $guide[] = [$region->code, $region->name];
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
        $regionIds = PriceRegion::query()->pluck('id', 'code')->all();
        $errors = [];
        $records = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $productCode = $this->text($row['kode_product'] ?? null);
            $regionCode = $this->text($row['kode_region_harga'] ?? null);
            $rowErrors = [];

            if ($productCode === '' || ! isset($productIds[$productCode])) {
                $rowErrors[] = $productCode === '' ? 'kode_product wajib diisi' : "kode_product {$productCode} tidak ditemukan";
            }

            if ($regionCode === '' || ! isset($regionIds[$regionCode])) {
                $rowErrors[] = $regionCode === '' ? 'kode_region_harga wajib diisi' : "kode_region_harga {$regionCode} tidak ditemukan";
            }

            $price = $this->amount($row['harga'] ?? null, 'harga', true, $rowErrors);
            $regularPrice = $this->amount($row['harga_reguler'] ?? null, 'harga_reguler', false, $rowErrors);
            $validFrom = $this->date($row['berlaku_mulai'] ?? null, 'berlaku_mulai', $rowErrors);
            $validUntil = $this->date($row['berlaku_sampai'] ?? null, 'berlaku_sampai', $rowErrors);
            $active = $this->boolean($row['aktif'] ?? null);

            if ($validFrom !== null && $validUntil !== null && $validUntil < $validFrom) {
                $rowErrors[] = 'berlaku_sampai tidak boleh sebelum berlaku_mulai';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            $key = "{$productCode}|{$regionCode}|{$validFrom}";

            if ($rowErrors === [] && isset($seen[$key])) {
                $rowErrors[] = 'kombinasi product, region harga, dan berlaku_mulai muncul lebih dari sekali di file';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $seen[$key] = true;
            $records[] = [
                'product_id' => $productIds[$productCode],
                'price_region_id' => $regionIds[$regionCode],
                'price' => $price,
                'regular_price' => $regularPrice,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_active' => $active,
            ];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }

    /**
     * Nilai uang sebagai string desimal (maks. 4 desimal) agar tidak melewati float saat disimpan.
     *
     * @param  array<int, string>  $rowErrors
     */
    private function amount(mixed $value, string $column, bool $required, array &$rowErrors): ?string
    {
        $text = $this->text($value);

        if ($text === '') {
            if ($required) {
                $rowErrors[] = "{$column} wajib diisi";
            }

            return null;
        }

        if (is_float($value)) {
            $text = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
        }

        // Maks. 14 digit bulat + 4 desimal = batas numeric(18,4).
        if (! preg_match('/^\d{1,14}(\.\d{1,4})?$/', $text)) {
            $rowErrors[] = "{$column} harus angka >= 0 dengan maks. 4 desimal";

            return null;
        }

        return $text;
    }

    /**
     * Tanggal dari sel teks (YYYY-MM-DD) atau sel tanggal Excel (angka seri).
     *
     * @param  array<int, string>  $rowErrors
     */
    private function date(mixed $value, string $column, array &$rowErrors): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        $text = $this->text($value);

        if ($text === '') {
            return null;
        }

        try {
            if (is_numeric($text)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $text))->toDateString();
            }

            $date = Carbon::createFromFormat('!Y-m-d', $text);

            if ($date !== false && $date->format('Y-m-d') === $text) {
                return $text;
            }
        } catch (Throwable) {
            // Ditangani sebagai format tidak valid di bawah.
        }

        $rowErrors[] = "{$column} harus format YYYY-MM-DD";

        return null;
    }
}
