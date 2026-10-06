<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\OwnershipTypeRequest;
use App\Models\OwnershipType;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Jenis kepemilikan outlet (legacy GRUP2). Hak akses lewat menu `outlet-classifications`.
 */
class OwnershipTypeController extends Controller implements HasMiddleware
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
        $query = OwnershipType::query()
            ->select(['ownership_types.id', 'ownership_types.code', 'ownership_types.name', 'ownership_types.is_active'])
            ->withCount('outlets');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(OwnershipTypeRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Jenis kepemilikan berhasil dibuat',
            'data' => OwnershipType::query()->create($request->validated()),
        ], 201);
    }

    public function update(OwnershipTypeRequest $request, OwnershipType $ownershipType): JsonResponse
    {
        $ownershipType->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jenis kepemilikan berhasil diperbarui',
            'data' => $ownershipType,
        ]);
    }

    public function destroy(OwnershipType $ownershipType): JsonResponse
    {
        return $this->deleteOrReject(
            $ownershipType,
            'Jenis kepemilikan berhasil dihapus',
            'Jenis kepemilikan masih dipakai outlet. Nonaktifkan sebagai gantinya.',
        );
    }
}
