<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\City;
use App\Models\Outlet;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OwnershipType;
use App\Models\SalesArea;
use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Import Excel master outlet: upsert berdasarkan kode, all-or-nothing.
 * Area/kategori/kepemilikan/group dirujuk lewat kode. `kode_grup` kosong = group tidak diubah.
 */
class OutletImportService extends ExcelImporter
{
    public const COLUMNS = [
        'kode', 'nama', 'alamat', 'kode_kota', 'kode_area', 'kode_kategori', 'kode_kepemilikan', 'jumlah_bed', 'kode_grup',
        'crm_id', 'latitude', 'longitude', 'aktif',
    ];

    /**
     * Referensi kode -> kolom outlets.
     *
     * @var array<string, array{0: class-string<Model>, 1: string}>
     */
    private const REFERENCES = [
        'kode_kota' => [City::class, 'city_id'],
        'kode_area' => [SalesArea::class, 'area_id'],
        'kode_kategori' => [OutletCategory::class, 'outlet_category_id'],
        'kode_kepemilikan' => [OwnershipType::class, 'ownership_type_id'],
    ];

    public function __construct(private OutletService $outletService) {}

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
            ->withProperties([...AuditLog::requestContext(), 'type' => 'outlet', 'file' => $file->getClientOriginalName(), ...$result])
            ->log('Import outlet dari Excel');

        return $result;
    }

    public function template(): ImportTemplateExport
    {
        $guide = [
            ['kode', 'Wajib, maks. 30 karakter. Kode yang sudah ada akan diperbarui.'],
            ['nama', 'Wajib, maks. 255 karakter.'],
            ['alamat', 'Opsional.'],
            ['kode_kota', 'Opsional, kode kota/kabupaten (kode wilayah BPS, mis. 8271) dari menu Master Organisasi > Provinsi & Kota.'],
            ['kode_area', 'Opsional, kode area penjualan terdaftar (menu Master Organisasi > Area).'],
            ['kode_kategori', 'Opsional, kode kategori outlet (lihat daftar di bawah).'],
            ['kode_kepemilikan', 'Opsional, kode jenis kepemilikan (lihat daftar di bawah).'],
            ['jumlah_bed', 'Opsional, angka bulat >= 0.'],
            ['kode_grup', 'Opsional, kode group RS (lihat daftar di bawah). Kosong = group tidak diubah.'],
            ['crm_id', 'Opsional, angka ID outlet di CRM.'],
            ['latitude', 'Opsional, -90 s/d 90.'],
            ['longitude', 'Opsional, -180 s/d 180.'],
            ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'],
        ];

        foreach ([
            'Kategori' => OutletCategory::query(),
            'Kepemilikan' => OwnershipType::query(),
            'Group RS' => OutletGroup::query()->whereNotNull('code'),
        ] as $label => $query) {
            $guide[] = [null, null];
            $guide[] = ["Kode {$label}", "Nama {$label}"];

            foreach ($query->orderBy('code')->get(['code', 'name']) as $model) {
                $guide[] = [$model->code, $model->name];
            }
        }

        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', self::COLUMNS),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], $guide),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array{attributes: array<string, mixed>, group_id: int|null|false}>
     */
    private function validateRows(array $rows): array
    {
        $lookups = [];

        foreach (self::REFERENCES as $column => [$model]) {
            $lookups[$column] = $model::query()->pluck('id', 'code')->all();
        }

        $groupIds = OutletGroup::query()->whereNotNull('code')->pluck('id', 'code')->all();
        $errors = [];
        $records = [];

        foreach ($rows as $index => $row) {
            $code = $this->text($row['kode'] ?? null);
            $name = $this->text($row['nama'] ?? null);
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

            $address = $this->text($row['alamat'] ?? null);
            $attributes = ['code' => $code, 'name' => $name, 'address' => $address === '' ? null : $address];

            foreach (self::REFERENCES as $column => [, $attribute]) {
                $referenceCode = $this->text($row[$column] ?? null);

                if ($referenceCode === '') {
                    $attributes[$attribute] = null;
                } elseif (! isset($lookups[$column][$referenceCode])) {
                    $rowErrors[] = "{$column} {$referenceCode} tidak ditemukan";
                } else {
                    $attributes[$attribute] = $lookups[$column][$referenceCode];
                }
            }

            foreach ([
                'jumlah_bed' => ['bed_count', 0, null, true],
                'crm_id' => ['crm_id', 1, null, true],
                'latitude' => ['latitude', -90, 90, false],
                'longitude' => ['longitude', -180, 180, false],
            ] as $column => [$attribute, $min, $max, $integer]) {
                $value = $this->text($row[$column] ?? null);

                if ($value === '') {
                    $attributes[$attribute] = null;
                } elseif (! is_numeric($value) || ($integer && ! ctype_digit(ltrim($value, '-'))) || $value < $min || ($max !== null && $value > $max)) {
                    $range = $max === null ? ">= {$min}" : "{$min} s/d {$max}";
                    $rowErrors[] = "{$column} harus ".($integer ? 'angka bulat' : 'angka')." {$range}";
                } else {
                    $attributes[$attribute] = $integer ? (int) $value : $value;
                }
            }

            // false = kolom kosong, group tidak diubah.
            $groupCode = $this->text($row['kode_grup'] ?? null);
            $groupId = false;

            if ($groupCode !== '') {
                if (! isset($groupIds[$groupCode])) {
                    $rowErrors[] = "kode_grup {$groupCode} tidak ditemukan";
                } else {
                    $groupId = $groupIds[$groupCode];
                }
            }

            $active = $this->boolean($row['aktif'] ?? null);

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            } else {
                $attributes['is_active'] = $active;
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';
            } elseif ($code !== '') {
                $records[$code] = ['attributes' => $attributes, 'group_id' => $groupId];
            }
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }

    /**
     * @param  array<string, array{attributes: array<string, mixed>, group_id: int|null|false}>  $records
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function upsert(array $records): array
    {
        $existing = Outlet::query()->whereIn('code', $this->codes($records))->with('activeGroupMembership')->get()->keyBy('code');
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($records as $code => ['attributes' => $attributes, 'group_id' => $groupId]) {
            $outlet = $existing->get((string) $code) ?? new Outlet;
            $isNew = ! $outlet->exists;
            $outlet->fill($attributes);

            $groupChanged = $groupId !== false && $outlet->activeGroupMembership?->outlet_group_id !== $groupId;

            if (! $isNew && ! $outlet->isDirty() && ! $groupChanged) {
                $result['unchanged']++;

                continue;
            }

            $outlet->save();

            if ($groupId !== false) {
                $this->outletService->syncGroup($outlet, $groupId);
            }

            $result[$isNew ? 'created' : 'updated']++;
        }

        return $result;
    }
}
