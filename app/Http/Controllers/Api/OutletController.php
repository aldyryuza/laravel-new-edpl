<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\OutletRequest;
use App\Models\Outlet;
use App\Services\OutletImportService;
use App\Services\OutletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class OutletController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /** Relasi yang ditampilkan di tabel/form. */
    private const RELATIONS = [
        'city:id,code,name',
        'area:id,code,name,region_id',
        'area.region:id,code,name',
        'category:id,code,name',
        'ownershipType:id,code,name',
        'activeGroupMembership.outletGroup:id,code,name',
    ];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:outlets.read', only: ['datatable', 'show']),
            new Middleware('can:outlets.create', only: ['store']),
            new Middleware('can:outlets.update', only: ['update']),
            new Middleware('can:outlets.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:outlets.create', 'can:outlets.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Filter opsional: region_id, outlet_category_id, ownership_type_id,
     * outlet_group_id (group aktif), is_active.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = Outlet::query()
            ->visibleTo($request->user())
            ->select([
                'outlets.id', 'outlets.code', 'outlets.name', 'outlets.address', 'outlets.city_id', 'outlets.area_id', 'outlets.outlet_category_id',
                'outlets.ownership_type_id', 'outlets.bed_count', 'outlets.crm_id', 'outlets.latitude', 'outlets.longitude', 'outlets.is_active',
            ])
            ->with(self::RELATIONS)
            ->when($request->integer('region_id'), fn (Builder $query, int $regionId) => $query
                ->whereHas('area', fn (Builder $area) => $area->where('region_id', $regionId)))
            ->when($request->integer('outlet_category_id'), fn (Builder $query, int $id) => $query->where('outlet_category_id', $id))
            ->when($request->integer('ownership_type_id'), fn (Builder $query, int $id) => $query->where('ownership_type_id', $id))
            ->when($request->integer('outlet_group_id'), fn (Builder $query, int $groupId) => $query
                ->whereHas('activeGroupMembership', fn (Builder $membership) => $membership->where('outlet_group_id', $groupId)))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'bed_count', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('outlets.code', "%{$term}%")
                        ->orWhereLike('outlets.name', "%{$term}%"));
                }
            })
            ->toJson();
    }

    public function store(OutletRequest $request, OutletService $outletService): JsonResponse
    {
        $outlet = $outletService->save(new Outlet, $request->outletAttributes(), $request->groupId());

        return response()->json([
            'success' => true,
            'message' => 'Outlet berhasil dibuat',
            'data' => $outlet->load(self::RELATIONS),
        ], 201);
    }

    public function show(Request $request, Outlet $outlet): JsonResponse
    {
        $this->ensureVisible($request, $outlet);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $outlet->load([...self::RELATIONS, 'groupMemberships.outletGroup:id,code,name']),
        ]);
    }

    public function update(OutletRequest $request, Outlet $outlet, OutletService $outletService): JsonResponse
    {
        $this->ensureVisible($request, $outlet);

        $outletService->save($outlet, $request->outletAttributes(), $request->groupId());

        return response()->json([
            'success' => true,
            'message' => 'Outlet berhasil diperbarui',
            'data' => $outlet->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode), hanya untuk user Nasional karena bisa menyentuh semua wilayah. Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, OutletImportService $importService): JsonResponse
    {
        abort_unless($request->user()->regionScope()->isNational(), 403, 'Import outlet hanya untuk user dengan wilayah Nasional.');

        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Outlet selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Histori keanggotaan group ikut dihapus (tidak bermakna tanpa outlet). Outlet yang sudah dipakai
     * DPL dijaga FK RESTRICT sehingga seluruh penghapusan dibatalkan.
     */
    public function destroy(Request $request, Outlet $outlet): JsonResponse
    {
        $this->ensureVisible($request, $outlet);

        return $this->deleteOrReject(
            $outlet,
            'Outlet berhasil dihapus',
            'Outlet sudah dipakai pada data lain (mis. DPL). Nonaktifkan outlet ini sebagai gantinya.',
            fn () => $outlet->groupMemberships()->delete(),
        );
    }

    /**
     * Outlet di luar wilayah user diperlakukan seperti tidak ada (404).
     */
    private function ensureVisible(Request $request, Outlet $outlet): void
    {
        abort_unless(Outlet::query()->visibleTo($request->user())->whereKey($outlet->getKey())->exists(), 404);
    }
}
