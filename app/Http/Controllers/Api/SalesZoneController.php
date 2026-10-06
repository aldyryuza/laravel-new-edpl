<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\SalesZoneRequest;
use App\Models\SalesZone;
use App\Services\CodeMasterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class SalesZoneController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:sales-zones.read', only: ['datatable', 'show']),
            new Middleware('can:sales-zones.create', only: ['store']),
            new Middleware('can:sales-zones.update', only: ['update']),
            new Middleware('can:sales-zones.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:sales-zones.create', 'can:sales-zones.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra, protokol DataTables server-side).
     */
    public function datatable(): JsonResponse
    {
        $query = SalesZone::query()
            ->select(['sales_zones.id', 'sales_zones.code', 'sales_zones.name', 'sales_zones.is_active'])
            ->withCount('regions');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(SalesZoneRequest $request): JsonResponse
    {
        $zone = SalesZone::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Zona berhasil dibuat',
            'data' => $zone,
        ], 201);
    }

    public function show(SalesZone $salesZone): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $salesZone->loadCount('regions'),
        ]);
    }

    public function update(SalesZoneRequest $request, SalesZone $salesZone): JsonResponse
    {
        $salesZone->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Zona berhasil diperbarui',
            'data' => $salesZone,
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, CodeMasterImportService $importService): JsonResponse
    {
        $result = $importService->import(CodeMasterImportService::ZONE, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Zona selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(SalesZone $salesZone): JsonResponse
    {
        return $this->deleteOrReject(
            $salesZone,
            'Zona berhasil dihapus',
            'Zona masih dipakai (region atau approver). Nonaktifkan zona ini sebagai gantinya.',
        );
    }
}
