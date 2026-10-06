<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\ProvinceRequest;
use App\Models\Province;
use App\Services\CodeMasterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class ProvinceController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:provinces.read', only: ['datatable']),
            new Middleware('can:provinces.create', only: ['store']),
            new Middleware('can:provinces.update', only: ['update']),
            new Middleware('can:provinces.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:provinces.create', 'can:provinces.update'], only: ['import']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = Province::query()
            ->select(['provinces.id', 'provinces.code', 'provinces.name', 'provinces.is_active'])
            ->withCount('cities');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(ProvinceRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Provinsi berhasil dibuat',
            'data' => Province::query()->create($request->validated()),
        ], 201);
    }

    public function update(ProvinceRequest $request, Province $province): JsonResponse
    {
        $province->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Provinsi berhasil diperbarui',
            'data' => $province,
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, CodeMasterImportService $importService): JsonResponse
    {
        $result = $importService->import(CodeMasterImportService::PROVINCE, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Provinsi selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(Province $province): JsonResponse
    {
        return $this->deleteOrReject(
            $province,
            'Provinsi berhasil dihapus',
            'Provinsi masih memiliki kota. Nonaktifkan provinsi ini sebagai gantinya.',
        );
    }
}
