<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\SalesAreaRequest;
use App\Models\SalesArea;
use App\Services\CodeMasterImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class SalesAreaController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:sales-areas.read', only: ['datatable', 'show']),
            new Middleware('can:sales-areas.create', only: ['store']),
            new Middleware('can:sales-areas.update', only: ['update']),
            new Middleware('can:sales-areas.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:sales-areas.create', 'can:sales-areas.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian juga mencakup nama region/zona; filter opsional `region_id`.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = SalesArea::query()
            ->select(['sales_areas.id', 'sales_areas.code', 'sales_areas.name', 'sales_areas.region_id', 'sales_areas.is_active'])
            ->with('region:id,code,name,zone_id', 'region.zone:id,code,name')
            ->when($request->integer('region_id'), fn (Builder $query, int $regionId) => $query->where('region_id', $regionId));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term === '') {
                    return;
                }

                $query->where(fn (Builder $query) => $query
                    ->whereLike('sales_areas.code', "%{$term}%")
                    ->orWhereLike('sales_areas.name', "%{$term}%")
                    ->orWhereHas('region', fn (Builder $region) => $region
                        ->whereLike('name', "%{$term}%")
                        ->orWhereLike('code', "%{$term}%")
                        ->orWhereHas('zone', fn (Builder $zone) => $zone->whereLike('name', "%{$term}%"))));
            })
            ->toJson();
    }

    public function store(SalesAreaRequest $request): JsonResponse
    {
        $area = SalesArea::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Area berhasil dibuat',
            'data' => $area->load('region:id,code,name'),
        ], 201);
    }

    public function show(SalesArea $salesArea): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $salesArea->load('region:id,code,name,zone_id', 'region.zone:id,code,name'),
        ]);
    }

    public function update(SalesAreaRequest $request, SalesArea $salesArea): JsonResponse
    {
        $salesArea->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Area berhasil diperbarui',
            'data' => $salesArea->load('region:id,code,name'),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, CodeMasterImportService $importService): JsonResponse
    {
        $result = $importService->import(CodeMasterImportService::AREA, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Area selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(SalesArea $salesArea): JsonResponse
    {
        return $this->deleteOrReject(
            $salesArea,
            'Area berhasil dihapus',
            'Area masih dipakai (outlet atau scope user). Nonaktifkan area ini sebagai gantinya.',
        );
    }
}
