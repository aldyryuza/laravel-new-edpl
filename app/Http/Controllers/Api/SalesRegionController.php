<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\SalesRegionRequest;
use App\Models\SalesRegion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class SalesRegionController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:sales-regions.read', only: ['datatable', 'show']),
            new Middleware('can:sales-regions.create', only: ['store']),
            new Middleware('can:sales-regions.update', only: ['update']),
            new Middleware('can:sales-regions.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian juga mencakup nama/kode zona; filter opsional `zone_id`.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = SalesRegion::query()
            ->select(['sales_regions.id', 'sales_regions.code', 'sales_regions.name', 'sales_regions.zone_id', 'sales_regions.is_active'])
            ->with('zone:id,code,name')
            ->withCount('areas')
            ->when($request->integer('zone_id'), fn (Builder $query, int $zoneId) => $query->where('zone_id', $zoneId));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term === '') {
                    return;
                }

                $query->where(fn (Builder $query) => $query
                    ->whereLike('sales_regions.code', "%{$term}%")
                    ->orWhereLike('sales_regions.name', "%{$term}%")
                    ->orWhereHas('zone', fn (Builder $zone) => $zone->whereLike('name', "%{$term}%")->orWhereLike('code', "%{$term}%")));
            })
            ->toJson();
    }

    public function store(SalesRegionRequest $request): JsonResponse
    {
        $region = SalesRegion::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Region berhasil dibuat',
            'data' => $region->load('zone:id,code,name'),
        ], 201);
    }

    public function show(SalesRegion $salesRegion): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $salesRegion->load('zone:id,code,name')->loadCount('areas'),
        ]);
    }

    public function update(SalesRegionRequest $request, SalesRegion $salesRegion): JsonResponse
    {
        $salesRegion->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Region berhasil diperbarui',
            'data' => $salesRegion->load('zone:id,code,name'),
        ]);
    }

    public function destroy(SalesRegion $salesRegion): JsonResponse
    {
        return $this->deleteOrReject(
            $salesRegion,
            'Region berhasil dihapus',
            'Region masih dipakai (area, distributor, atau approver). Nonaktifkan region ini sebagai gantinya.',
        );
    }
}
