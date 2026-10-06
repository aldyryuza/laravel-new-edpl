<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Dpl\PriceRegion;
use App\Services\PriceRegionMappingService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

/**
 * Region harga per outlet / distributor. Tipe (`outlets` | `distributors`) datang dari route defaults;
 * hak akses (`outlet-price-regions.*` / `distributor-price-regions.*`) dipasang di routes/api.php.
 */
class PriceRegionMappingController extends Controller
{
    public function __construct(private PriceRegionMappingService $mappingService) {}

    /**
     * Data tabel: outlet/distributor beserta region harganya. Filter opsional `price_region_id`
     * (angka, atau `none` untuk yang belum punya region harga).
     */
    public function datatable(Request $request, string $type): JsonResponse
    {
        $definition = $this->mappingService->definition($type);
        $owner = $definition['table'];
        $mappingTable = (new $definition['mapping'])->getTable();

        $query = DB::table($owner)
            ->leftJoin("{$mappingTable} as mapping", 'mapping.'.$definition['foreign_key'], '=', "{$owner}.id")
            ->leftJoin('dpl.price_regions as price_region', 'price_region.id', '=', 'mapping.price_region_id')
            ->select([
                "{$owner}.id", "{$owner}.code", "{$owner}.name", "{$owner}.is_active",
                'price_region.id as price_region_id', 'price_region.code as price_region_code', 'price_region.name as price_region_name',
            ])
            ->when($request->input('price_region_id') === 'none', fn (Builder $query) => $query->whereNull('mapping.id'))
            ->when($request->integer('price_region_id'), fn (Builder $query, int $id) => $query->where('mapping.price_region_id', $id));

        return DataTables::query($query)
            ->whitelist(["{$owner}.code", "{$owner}.name"])
            ->filter(function (Builder $query) use ($request, $owner): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike("{$owner}.code", "%{$term}%")
                        ->orWhereLike("{$owner}.name", "%{$term}%"));
                }
            })
            ->toJson();
    }

    /**
     * Pasang/ganti region harga satu outlet/distributor; `price_region_id` kosong = hapus.
     */
    public function update(Request $request): JsonResponse
    {
        // Dibaca per nama: urutan argumen controller mengikuti urutan parameter route ({id} lalu default `type`).
        $type = (string) $request->route('type');
        $definition = $this->mappingService->definition($type);
        $owner = $definition['owner']::query()->findOrFail((int) $request->route('id'));

        $validated = $request->validate(
            ['price_region_id' => ['nullable', 'integer', Rule::exists(PriceRegion::class, 'id')]],
            [],
            ['price_region_id' => 'region harga'],
        );

        $this->mappingService->assign($type, $owner, $validated['price_region_id'] ?? null);

        return response()->json([
            'success' => true,
            'message' => "Region harga {$definition['label']} {$owner->code} berhasil disimpan",
            'data' => null,
        ]);
    }

    /**
     * Import Excel. Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, string $type): JsonResponse
    {
        $result = $this->mappingService->import($type, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }
}
