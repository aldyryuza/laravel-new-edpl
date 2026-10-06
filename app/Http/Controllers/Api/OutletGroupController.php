<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\OutletGroupRequest;
use App\Models\OutletGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Group RS / group outlet (legacy GRUP3). Keanggotaan diatur dari form/import outlet.
 */
class OutletGroupController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:outlet-groups.read', only: ['datatable']),
            new Middleware('can:outlet-groups.create', only: ['store']),
            new Middleware('can:outlet-groups.update', only: ['update']),
            new Middleware('can:outlet-groups.delete', only: ['destroy']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = OutletGroup::query()
            ->select(['outlet_groups.id', 'outlet_groups.code', 'outlet_groups.name', 'outlet_groups.description', 'outlet_groups.is_active'])
            ->withCount(['members as active_members_count' => fn (Builder $query) => $query->whereNull('valid_until')]);

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->toJson();
    }

    public function store(OutletGroupRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Group outlet berhasil dibuat',
            'data' => OutletGroup::query()->create($request->validated()),
        ], 201);
    }

    public function update(OutletGroupRequest $request, OutletGroup $outletGroup): JsonResponse
    {
        $outletGroup->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Group outlet berhasil diperbarui',
            'data' => $outletGroup,
        ]);
    }

    public function destroy(OutletGroup $outletGroup): JsonResponse
    {
        return $this->deleteOrReject(
            $outletGroup,
            'Group outlet berhasil dihapus',
            'Group masih memiliki histori anggota atau dipakai harga khusus. Nonaktifkan group ini sebagai gantinya.',
        );
    }
}
