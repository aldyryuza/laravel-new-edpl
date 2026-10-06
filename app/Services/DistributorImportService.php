<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Distributor;
use App\Models\SalesRegion;
use App\Support\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel master distributor: upsert berdasarkan kode, all-or-nothing.
 *
 * - `kode_induk` boleh merujuk distributor di database atau di file yang sama; kosong = tanpa induk.
 * - `kode_region` berisi satu/lebih kode region dipisah koma; kosong = region tidak diubah.
 * - Hierarki melingkar ditolak sebelum ada data yang disimpan.
 */
class DistributorImportService extends ExcelImporter
{
    public const COLUMNS = [
        'kode', 'nama', 'grup_distributor', 'kota', 'alamat', 'telepon', 'fax', 'email', 'npwp', 'latitude', 'longitude',
        'radius_meter', 'cabang', 'kode_induk', 'kode_region', 'netsuite_id', 'aktif',
    ];

    /**
     * Kolom teks opsional: kolom Excel => [kolom distributors, panjang maksimal].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const TEXT_COLUMNS = [
        'grup_distributor' => ['group_code', 50],
        'kota' => ['city_name', 150],
        'alamat' => ['address', 2000],
        'telepon' => ['phone', 50],
        'fax' => ['fax', 50],
        'email' => ['email', 255],
        'npwp' => ['npwp', 30],
        'netsuite_id' => ['netsuite_id', 255],
    ];

    /** Batas kedalaman penelusuran induk, pengaman bila data lama sudah melingkar. */
    private const MAX_DEPTH = 50;

    public function __construct(private DistributorService $distributorService) {}

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(UploadedFile $file): array
    {
        $records = $this->validateRows($this->readRows($file, ['kode', 'nama']));

        $result = DB::transaction(fn () => $this->upsert($records));

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => 'distributor', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import distributor dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        $guide = [
            ['kode', 'Wajib, maks. 30 karakter. Kode yang sudah ada akan diperbarui.'],
            ['nama', 'Wajib, maks. 255 karakter.'],
            ['grup_distributor', 'Opsional, maks. 50 karakter (pengelompokan legacy).'],
            ['kota', 'Opsional, maks. 150 karakter.'],
            ['alamat', 'Opsional.'],
            ['telepon', 'Opsional, maks. 50 karakter.'],
            ['fax', 'Opsional, maks. 50 karakter.'],
            ['email', 'Opsional, alamat email valid.'],
            ['npwp', 'Opsional, maks. 30 karakter.'],
            ['latitude', 'Opsional, -90 s/d 90. Diisi bersama longitude.'],
            ['longitude', 'Opsional, -180 s/d 180. Diisi bersama latitude.'],
            ['radius_meter', 'Opsional, radius lokasi monthly meeting CRM dalam meter (angka bulat >= 0).'],
            ['cabang', 'Opsional: 1/ya atau 0/tidak. Kosong = tidak.'],
            ['kode_induk', 'Opsional, kode distributor induk (boleh distributor di file yang sama). Kosong = tanpa induk.'],
            ['kode_region', 'Opsional, satu atau lebih kode region dipisah koma, mis. R-JABAR,R-DKI. Kosong = region tidak diubah.'],
            ['netsuite_id', 'Opsional.'],
            ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
            [null, null],
            ['Kode Region', 'Nama Region'],
        ];

        foreach (SalesRegion::query()->orderBy('code')->get(['code', 'name']) as $region) {
            $guide[] = [$region->code, $region->name];
        }

        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], $guide),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{attributes: array<string, mixed>, parent_code: string|null, region_ids: array<int, int>|null}>
     */
    private function validateRows(array $rows): array
    {
        $regionIds = SalesRegion::query()->pluck('id', 'code')->all();
        // Induk saat ini di database: kode -> kode induk.
        $parentOf = DB::table('distributors')
            ->leftJoin('distributors as parents', 'parents.id', '=', 'distributors.parent_distributor_id')
            ->select(['distributors.code as code', 'parents.code as parent_code'])
            ->pluck('parent_code', 'code')
            ->all();
        $fileCodes = [];

        foreach ($rows as $row) {
            $fileCodes[$this->text($row['kode'] ?? null)] = true;
        }

        $errors = [];
        $records = [];
        $lines = [];

        foreach ($rows as $index => $row) {
            $code = $this->text($row['kode'] ?? null);
            $name = $this->text($row['nama'] ?? null);
            $parentCode = $this->text($row['kode_induk'] ?? null);
            $latitude = $this->text($row['latitude'] ?? null);
            $longitude = $this->text($row['longitude'] ?? null);
            $radius = $this->text($row['radius_meter'] ?? null);
            $isBranch = $this->boolean($row['cabang'] ?? null, default: false);
            $active = $this->boolean($row['aktif'] ?? null);
            $rowErrors = [];

            if ($code === '') {
                $rowErrors[] = 'kode wajib diisi';
            } elseif (mb_strlen($code) > 30) {
                $rowErrors[] = 'kode maksimal 30 karakter';
            } elseif (isset($records[$code])) {
                $rowErrors[] = "kode {$code} muncul lebih dari sekali di file";
            }

            if ($name === '') {
                $rowErrors[] = 'nama wajib diisi';
            } elseif (mb_strlen($name) > 255) {
                $rowErrors[] = 'nama maksimal 255 karakter';
            }

            $attributes = ['code' => $code, 'name' => $name];

            foreach (self::TEXT_COLUMNS as $column => [$attribute, $max]) {
                $value = $this->text($row[$column] ?? null);
                $attributes[$attribute] = $value === '' ? null : $value;

                if (mb_strlen($value) > $max) {
                    $rowErrors[] = "{$column} maksimal {$max} karakter";
                }
            }

            if ($attributes['email'] !== null && filter_var($attributes['email'], FILTER_VALIDATE_EMAIL) === false) {
                $rowErrors[] = 'email tidak valid';
            }

            if (($latitude === '') !== ($longitude === '')) {
                $rowErrors[] = 'latitude dan longitude harus diisi bersamaan';
            } elseif ($latitude !== '' && (! is_numeric($latitude) || abs((float) $latitude) > 90 || ! is_numeric($longitude) || abs((float) $longitude) > 180)) {
                $rowErrors[] = 'latitude harus -90 s/d 90 dan longitude -180 s/d 180';
            }

            $attributes['latitude'] = $latitude === '' ? null : $latitude;
            $attributes['longitude'] = $longitude === '' ? null : $longitude;

            if ($radius !== '' && (! ctype_digit($radius) || (int) $radius > 100000)) {
                $rowErrors[] = 'radius_meter harus angka bulat 0 s/d 100000';
            }

            $attributes['meeting_radius_meters'] = $radius === '' ? null : (int) $radius;

            if ($parentCode !== '' && ! array_key_exists($parentCode, $parentOf) && ! isset($fileCodes[$parentCode])) {
                $rowErrors[] = "kode_induk {$parentCode} tidak ditemukan";
            }

            $rowRegionIds = null;
            $regionCodes = array_filter(array_map('trim', explode(',', $this->text($row['kode_region'] ?? null))), fn (string $value) => $value !== '');

            if ($regionCodes !== []) {
                $rowRegionIds = [];

                foreach (array_unique($regionCodes) as $regionCode) {
                    if (! isset($regionIds[$regionCode])) {
                        $rowErrors[] = "kode_region {$regionCode} tidak ditemukan";
                    } else {
                        $rowRegionIds[] = $regionIds[$regionCode];
                    }
                }
            }

            if ($isBranch === null) {
                $rowErrors[] = 'cabang harus 1/ya atau 0/tidak';
            }

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $lines[$code] = $this->lineNumber($index);
            $records[$code] = [
                'attributes' => [...$attributes, 'is_branch' => $isBranch, 'is_active' => $active],
                'parent_code' => $parentCode === '' ? null : $parentCode,
                'region_ids' => $rowRegionIds,
            ];
        }

        // Hierarki akhir = database ditimpa isi file; tolak bila ada yang melingkar.
        foreach ($records as $code => $record) {
            $parentOf[(string) $code] = $record['parent_code'];
        }

        foreach (array_keys($records) as $code) {
            if ($this->isCyclic((string) $code, $parentOf)) {
                $errors[] = 'Baris '.$lines[$code].": kode_induk membuat hierarki melingkar (induk adalah {$code} sendiri atau cabangnya).";
            }
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }

    /**
     * @param  array<string, string|null>  $parentOf  kode -> kode induk
     */
    private function isCyclic(string $code, array $parentOf): bool
    {
        $current = $parentOf[$code] ?? null;

        for ($depth = 0; $current !== null && $depth < self::MAX_DEPTH; $depth++) {
            if ($current === $code) {
                return true;
            }

            $current = $parentOf[$current] ?? null;
        }

        return $current !== null;
    }

    /**
     * Dua tahap: simpan semua distributor dulu (induk di file yang sama sudah punya ID), lalu pasang induk & region.
     *
     * @param  array<string, array{attributes: array<string, mixed>, parent_code: string|null, region_ids: array<int, int>|null}>  $records
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function upsert(array $records): array
    {
        $codes = $this->codes($records);
        $parentCodes = array_values(array_filter(array_column($records, 'parent_code')));
        $models = Distributor::query()->whereIn('code', [...$codes, ...$parentCodes])->get()->keyBy('code');
        $status = [];

        foreach ($records as $code => $record) {
            $code = (string) $code;
            $distributor = $models->get($code) ?? new Distributor;
            $isNew = ! $distributor->exists;
            $distributor->fill($record['attributes']);
            $status[$code] = $isNew ? 'created' : ($distributor->isDirty() ? 'updated' : 'unchanged');

            if ($status[$code] !== 'unchanged') {
                $distributor->save();
            }

            $models->put($code, $distributor);
        }

        foreach ($records as $code => $record) {
            $code = (string) $code;
            $distributor = $models->get($code);
            $distributor->parent_distributor_id = $record['parent_code'] === null ? null : $models->get($record['parent_code'])->id;

            if ($distributor->isDirty('parent_distributor_id')) {
                $distributor->save();
                $status[$code] = $status[$code] === 'unchanged' ? 'updated' : $status[$code];
            }

            if ($record['region_ids'] !== null && $this->distributorService->syncRegions($distributor, $record['region_ids'])) {
                $status[$code] = $status[$code] === 'unchanged' ? 'updated' : $status[$code];
            }
        }

        $counts = array_count_values($status);

        return ['created' => $counts['created'] ?? 0, 'updated' => $counts['updated'] ?? 0, 'unchanged' => $counts['unchanged'] ?? 0];
    }
}
