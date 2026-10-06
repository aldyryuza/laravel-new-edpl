<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Imports\SheetRowsImport;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import Excel master organisasi (zona, region, area) dengan aturan:
 * - kode yang sudah ada diperbarui, kode baru dibuat (upsert berdasarkan kode);
 * - semua baris divalidasi dulu; bila ada satu saja error, tidak ada yang disimpan;
 * - parent (zona/region) dirujuk lewat kode, bukan ID.
 */
class OrganizationImportService
{
    public const ZONE = 'zone';

    public const REGION = 'region';

    public const AREA = 'area';

    public const MAX_ROWS = 5000;

    /** Batas jumlah pesan error yang dikembalikan agar respons tetap ringkas. */
    private const MAX_ERRORS = 100;

    private const TRUE_VALUES = ['1', 'ya', 'y', 'aktif', 'true', 'yes'];

    private const FALSE_VALUES = ['0', 'tidak', 't', 'nonaktif', 'false', 'no'];

    /**
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(string $type, UploadedFile $file): array
    {
        $definition = $this->definition($type);
        $rows = Excel::toArray(new SheetRowsImport, $file)[0] ?? [];

        $records = $this->validateRows($definition, $rows);

        $result = DB::transaction(fn () => $this->upsert($definition['model'], $records));

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => $type, 'file' => $file->getClientOriginalName(), ...$result])
            ->log("Import {$definition['label']} dari Excel");

        return $result;
    }

    /**
     * Template Excel: sheet "Data" (judul kolom saja) + sheet "Petunjuk" (keterangan kolom dan kode parent yang tersedia).
     */
    public function template(string $type): ImportTemplateExport
    {
        $definition = $this->definition($type);
        $parent = $definition['parent'];

        $guide = [
            ['kode', 'Wajib, maks. 30 karakter. Kode yang sudah ada akan diperbarui.'],
            ['nama', 'Wajib, maks. 100 karakter.'],
        ];

        if ($parent !== null) {
            $guide[] = [$parent['column'], "Wajib, kode {$parent['label']} yang sudah terdaftar (lihat daftar di bawah)."];
        }

        $guide[] = ['aktif', 'Opsional: 1/ya/aktif atau 0/tidak/nonaktif. Kosong = aktif.'];

        if ($parent !== null) {
            $guide[] = [null, null];
            $guide[] = ["Kode {$parent['label']}", "Nama {$parent['label']}"];

            foreach ($parent['model']::query()->orderBy('code')->get(['code', 'name']) as $model) {
                $guide[] = [$model->code, $model->name];
            }
        }

        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', $this->columns($definition)),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], $guide),
        ]);
    }

    /**
     * @return array{model: class-string<Model>, label: string, parent: array{column: string, model: class-string<Model>, key: string, label: string}|null}
     */
    private function definition(string $type): array
    {
        return match ($type) {
            self::ZONE => ['model' => SalesZone::class, 'label' => 'zona', 'parent' => null],
            self::REGION => [
                'model' => SalesRegion::class,
                'label' => 'region',
                'parent' => ['column' => 'kode_zona', 'model' => SalesZone::class, 'key' => 'zone_id', 'label' => 'zona'],
            ],
            self::AREA => [
                'model' => SalesArea::class,
                'label' => 'area',
                'parent' => ['column' => 'kode_region', 'model' => SalesRegion::class, 'key' => 'region_id', 'label' => 'region'],
            ],
            default => throw new InvalidArgumentException("Tipe import tidak dikenal: {$type}"),
        };
    }

    /**
     * @param  array{parent: array{column: string}|null}  $definition
     * @return array<int, string>
     */
    private function columns(array $definition): array
    {
        return array_values(array_filter(['kode', 'nama', $definition['parent']['column'] ?? null, 'aktif']));
    }

    /**
     * Validasi seluruh baris; kembalikan data siap simpan per kode.
     *
     * @param  array{model: class-string<Model>, label: string, parent: array{column: string, model: class-string<Model>, key: string, label: string}|null}  $definition
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function validateRows(array $definition, array $rows): array
    {
        if ($rows === []) {
            $this->fail(['File tidak berisi data. Isi sheet pertama mulai baris ke-2 sesuai template.']);
        }

        $missing = array_diff(array_diff($this->columns($definition), ['aktif']), array_keys($rows[0]));

        if ($missing !== []) {
            $this->fail(['Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan template yang disediakan.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            $this->fail(['Maksimal '.self::MAX_ROWS.' baris per file.']);
        }

        $parent = $definition['parent'];
        $parentIds = $parent ? $parent['model']::query()->pluck('id', 'code')->all() : [];
        $errors = [];
        $records = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
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
            } elseif (mb_strlen($name) > 100) {
                $rowErrors[] = 'nama maksimal 100 karakter';
            }

            $record = ['code' => $code, 'name' => $name];

            if ($parent !== null) {
                $parentCode = $this->text($row[$parent['column']] ?? null);

                if ($parentCode === '') {
                    $rowErrors[] = "{$parent['column']} wajib diisi";
                } elseif (! isset($parentIds[$parentCode])) {
                    $rowErrors[] = "{$parent['column']} {$parentCode} tidak ditemukan";
                } else {
                    $record[$parent['key']] = $parentIds[$parentCode];
                }
            }

            $active = $this->boolean($row['aktif'] ?? null);

            if ($active === null) {
                $rowErrors[] = 'aktif harus 1/ya/aktif atau 0/tidak/nonaktif';
            } else {
                $record['is_active'] = $active;
            }

            if ($rowErrors !== []) {
                $errors[] = "Baris {$line}: ".implode('; ', $rowErrors).'.';
            } elseif ($code !== '') {
                $records[$code] = $record;
            }
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        return $records;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, array<string, mixed>>  $records
     * @return array{created: int, updated: int, unchanged: int}
     */
    private function upsert(string $modelClass, array $records): array
    {
        // Key array PHP untuk kode numerik ("101") menjadi integer; kembalikan ke string untuk kolom varchar.
        $codes = array_map('strval', array_keys($records));
        $existing = $modelClass::query()->whereIn('code', $codes)->get()->keyBy('code');
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($records as $code => $attributes) {
            $model = $existing->get((string) $code);

            if ($model === null) {
                $modelClass::query()->create($attributes);
                $result['created']++;

                continue;
            }

            $model->fill($attributes);

            if ($model->isDirty()) {
                $model->save();
                $result['updated']++;
            } else {
                $result['unchanged']++;
            }
        }

        return $result;
    }

    /**
     * Nilai sel sebagai teks. Angka bulat dari Excel (mis. 101.0) dikembalikan tanpa desimal.
     */
    private function text(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * Kosong = aktif. Mengembalikan null bila nilai tidak dikenali.
     */
    private function boolean(mixed $value): ?bool
    {
        $value = mb_strtolower($this->text(is_bool($value) ? (int) $value : $value));

        return match (true) {
            $value === '' => true,
            in_array($value, self::TRUE_VALUES, true) => true,
            in_array($value, self::FALSE_VALUES, true) => false,
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $errors
     *
     * @throws ValidationException
     */
    private function fail(array $errors): never
    {
        $count = count($errors);
        $messages = array_slice($errors, 0, self::MAX_ERRORS);

        if ($count > self::MAX_ERRORS) {
            $messages[] = '... dan '.($count - self::MAX_ERRORS).' error lainnya.';
        }

        throw ValidationException::withMessages(['file' => $messages]);
    }
}
