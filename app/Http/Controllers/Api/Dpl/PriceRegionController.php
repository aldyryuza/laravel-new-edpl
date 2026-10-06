<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\PriceRegionRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Dpl\PriceRegion;
use App\Services\CodeMasterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Region harga DPL (legacy dpl_price_region).
 */
class PriceRegionController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:price-regions.read', only: ['datatable']),
            new Middleware('can:price-regions.create', only: ['store']),
            new Middleware('can:price-regions.update', only: ['update']),
            new Middleware('can:price-regions.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:price-regions.create', 'can:price-regions.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra) beserta jumlah outlet, distributor, dan harga product yang memakai region ini.
     */
    public function datatable(): JsonResponse
    {
        $query = PriceRegion::query()
            ->select(['id', 'code', 'name', 'description', 'is_active'])
            ->withCount(['outlets', 'distributors', 'productPrices']);

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(PriceRegionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Region harga berhasil dibuat',
            'data' => PriceRegion::query()->create($request->validated()),
        ], 201);
    }

    public function update(PriceRegionRequest $request, PriceRegion $priceRegion): JsonResponse
    {
        $priceRegion->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Region harga berhasil diperbarui',
            'data' => $priceRegion,
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, CodeMasterImportService $importService): JsonResponse
    {
        $result = $importService->import(CodeMasterImportService::PRICE_REGION, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Region Harga selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(PriceRegion $priceRegion): JsonResponse
    {
        return $this->deleteOrReject(
            $priceRegion,
            'Region harga berhasil dihapus',
            'Region harga masih dipakai outlet, distributor, atau harga product. Nonaktifkan sebagai gantinya.',
        );
    }
}
