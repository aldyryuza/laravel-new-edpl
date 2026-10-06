<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\OutletCategoryRequest;
use App\Models\OutletCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Kategori outlet (legacy GRUP1). Hak akses lewat menu `outlet-classifications`.
 */
class OutletCategoryController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:outlet-classifications.read', only: ['datatable']),
            new Middleware('can:outlet-classifications.create', only: ['store']),
            new Middleware('can:outlet-classifications.update', only: ['update']),
            new Middleware('can:outlet-classifications.delete', only: ['destroy']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = OutletCategory::query()
            ->select(['outlet_categories.id', 'outlet_categories.code', 'outlet_categories.name', 'outlet_categories.is_active'])
            ->withCount('outlets');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(OutletCategoryRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Kategori outlet berhasil dibuat',
            'data' => OutletCategory::query()->create($request->validated()),
        ], 201);
    }

    public function update(OutletCategoryRequest $request, OutletCategory $outletCategory): JsonResponse
    {
        $outletCategory->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kategori outlet berhasil diperbarui',
            'data' => $outletCategory,
        ]);
    }

    public function destroy(OutletCategory $outletCategory): JsonResponse
    {
        return $this->deleteOrReject(
            $outletCategory,
            'Kategori outlet berhasil dihapus',
            'Kategori masih dipakai outlet. Nonaktifkan kategori ini sebagai gantinya.',
        );
    }
}
