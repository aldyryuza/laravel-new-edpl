<?php

namespace App\Services;

use App\Exports\ImportTemplateExport;
use App\Exports\ImportTemplateSheet;
use App\Models\Distributor;
use App\Models\Dpl\DistributorPriceRegion;
use App\Models\Dpl\OutletPriceRegion;
use App\Models\Dpl\PriceRegion;
use App\Models\Outlet;
use App\Support\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Region harga outlet & distributor untuk DPL (dpl.outlet_price_regions, dpl.distributor_price_regions),
 * masing-masing maksimal satu region harga. Prioritas bila region outlet dan distributor berbeda diatur
 * di kalkulator DPL (belum final), bukan di sini.
 */
class PriceRegionMappingService extends ExcelImporter
{
    public const OUTLET = 'outlets';

    public const DISTRIBUTOR = 'distributors';

    /**
     * @return array{owner: class-string<Model>, mapping: class-string<Model>, foreign_key: string, table: string, label: string, code_column: string}
     */
    public function definition(string $type): array
    {
        return match ($type) {
            self::OUTLET => [
                'owner' => Outlet::class, 'mapping' => OutletPriceRegion::class, 'foreign_key' => 'outlet_id',
                'table' => 'outlets', 'label' => 'outlet', 'code_column' => 'kode_outlet',
            ],
            self::DISTRIBUTOR => [
                'owner' => Distributor::class, 'mapping' => DistributorPriceRegion::class, 'foreign_key' => 'distributor_id',
                'table' => 'distributors', 'label' => 'distributor', 'code_column' => 'kode_distributor',
            ],
            default => throw new InvalidArgumentException("Tipe mapping tidak dikenal: {$type}"),
        };
    }

    /**
     * Pasang/ganti region harga; null = hapus mapping. Mengembalikan apakah ada perubahan.
     */
    public function assign(string $type, Model $owner, ?int $priceRegionId): bool
    {
        $definition = $this->definition($type);
        $mapping = $definition['mapping']::query()->where($definition['foreign_key'], $owner->getKey())->first();

        if ($priceRegionId === null) {
            $mapping?->delete();

            return $mapping !== null;
        }

        if ($mapping === null) {
            $definition['mapping']::query()->create([$definition['foreign_key'] => $owner->getKey(), 'price_region_id' => $priceRegionId]);

            return true;
        }

        $mapping->price_region_id = $priceRegionId;

        if (! $mapping->isDirty()) {
            return false;
        }

        $mapping->save();

        return true;
    }

    /**
     * Import Excel: kode outlet/distributor + kode region harga. Region kosong = mapping dihapus. All-or-nothing.
     *
     * @return array{created: int, updated: int, unchanged: int}
     *
     * @throws ValidationException bila file tidak valid; error per baris ada di key `file`
     */
    public function import(string $type, UploadedFile $file): array
    {
        $definition = $this->definition($type);
        $rows = $this->readRows($file, [$definition['code_column'], 'kode_region_harga']);
        $ownerIds = $definition['owner']::query()->pluck('id', 'code')->all();
        $regionIds = PriceRegion::query()->pluck('id', 'code')->all();
        $errors = [];
        $assignments = [];

        foreach ($rows as $index => $row) {
            $ownerCode = $this->text($row[$definition['code_column']] ?? null);
            $regionCode = $this->text($row['kode_region_harga'] ?? null);
            $rowErrors = [];

            if ($ownerCode === '') {
                $rowErrors[] = "{$definition['code_column']} wajib diisi";
            } elseif (! isset($ownerIds[$ownerCode])) {
                $rowErrors[] = "{$definition['code_column']} {$ownerCode} tidak ditemukan";
            } elseif (isset($assignments[$ownerIds[$ownerCode]])) {
                $rowErrors[] = "{$definition['code_column']} {$ownerCode} muncul lebih dari sekali di file";
            }

            if ($regionCode !== '' && ! isset($regionIds[$regionCode])) {
                $rowErrors[] = "kode_region_harga {$regionCode} tidak ditemukan";
            }

            if ($rowErrors !== []) {
                $errors[] = 'Baris '.$this->lineNumber($index).': '.implode('; ', $rowErrors).'.';

                continue;
            }

            $assignments[$ownerIds[$ownerCode]] = $regionCode === '' ? null : $regionIds[$regionCode];
        }

        if ($errors !== []) {
            $this->fail($errors);
        }

        $result = DB::transaction(function () use ($type, $definition, $assignments): array {
            $existing = $definition['mapping']::query()
                ->whereIn($definition['foreign_key'], array_keys($assignments))
                ->pluck($definition['foreign_key'])
                ->flip();
            $owners = $definition['owner']::query()->whereKey(array_keys($assignments))->get()->keyBy('id');
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($assignments as $ownerId => $priceRegionId) {
                $hadMapping = $existing->has($ownerId);
                $changed = $this->assign($type, $owners[$ownerId], $priceRegionId);

                $result[match (true) {
                    ! $changed => 'unchanged',
                    $hadMapping => 'updated',
                    default => 'created',
                }]++;
            }

            return $result;
        });

        activity(AuditLog::DATA)
            ->event('imported')
            ->withProperties([...AuditLog::requestContext(), 'type' => "{$definition['label']}_price_region", 'file' => $file->getClientOriginalName(), ...$result])
            ->log("Import region harga {$definition['label']} dari Excel");

        return $result;
    }

    public function template(string $type): ImportTemplateExport
    {
        $definition = $this->definition($type);
        $guide = [
            [$definition['code_column'], "Wajib, kode {$definition['label']} terdaftar."],
            ['kode_region_harga', 'Kode region harga (lihat daftar di bawah). Kosong = region harga '.$definition['label'].' dihapus.'],
            [null, null],
            ['Kode Region Harga', 'Nama Region Harga'],
        ];

        foreach (PriceRegion::query()->orderBy('code')->get(['code', 'name']) as $region) {
            $guide[] = [$region->code, $region->name];
        }

        return new ImportTemplateExport([
            new ImportTemplateSheet('Data', [$definition['code_column'], 'kode_region_harga']),
            new ImportTemplateSheet('Petunjuk', ['Kolom', 'Keterangan'], $guide),
        ]);
    }
}
