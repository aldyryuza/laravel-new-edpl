<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\DistributorRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Distributor;
use App\Services\DistributorImportService;
use App\Services\DistributorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class DistributorController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /** Relasi yang ditampilkan di tabel/form. */
    private const RELATIONS = ['parent:id,code,name', 'regions:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:distributors.read', only: ['datatable', 'show']),
            new Middleware('can:distributors.create', only: ['store']),
            new Middleware('can:distributors.update', only: ['update']),
            new Middleware('can:distributors.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:distributors.create', 'can:distributors.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Filter opsional: region_id, is_branch, is_active.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = Distributor::query()
            ->select([
                'distributors.id', 'distributors.code', 'distributors.name', 'distributors.group_code', 'distributors.city_name',
                'distributors.address', 'distributors.phone', 'distributors.fax', 'distributors.email', 'distributors.npwp',
                'distributors.latitude', 'distributors.longitude', 'distributors.meeting_radius_meters',
                'distributors.is_branch', 'distributors.parent_distributor_id', 'distributors.netsuite_id', 'distributors.is_active',
            ])
            ->with(self::RELATIONS)
            ->withCount('branches')
            ->when($request->integer('region_id'), fn (Builder $query, int $regionId) => $query
                ->whereHas('regions', fn (Builder $region) => $region->whereKey($regionId)))
            ->when($request->filled('is_branch'), fn (Builder $query) => $query->where('is_branch', $request->boolean('is_branch')))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'city_name', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('distributors.code', "%{$term}%")
                        ->orWhereLike('distributors.name', "%{$term}%")
                        ->orWhereLike('distributors.city_name', "%{$term}%")
                        ->orWhereLike('distributors.group_code', "%{$term}%"));
                }
            })
            ->toJson();
    }

    public function store(DistributorRequest $request, DistributorService $distributorService): JsonResponse
    {
        $distributor = $distributorService->save(new Distributor, $request->distributorAttributes(), $request->regionIds());

        return response()->json([
            'success' => true,
            'message' => 'Distributor berhasil dibuat',
            'data' => $distributor->load(self::RELATIONS),
        ], 201);
    }

    public function show(Distributor $distributor): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $distributor->load([...self::RELATIONS, 'branches:id,code,name,parent_distributor_id']),
        ]);
    }

    public function update(DistributorRequest $request, Distributor $distributor, DistributorService $distributorService): JsonResponse
    {
        $distributorService->save($distributor, $request->distributorAttributes(), $request->regionIds());

        return response()->json([
            'success' => true,
            'message' => 'Distributor berhasil diperbarui',
            'data' => $distributor->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, DistributorImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Distributor selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Pemetaan region ikut dihapus (tidak bermakna tanpa distributor). Distributor yang punya cabang
     * atau sudah dipakai DPL/surat keluar dijaga FK RESTRICT sehingga penghapusan dibatalkan.
     */
    public function destroy(Distributor $distributor): JsonResponse
    {
        return $this->deleteOrReject(
            $distributor,
            'Distributor berhasil dihapus',
            'Distributor masih punya cabang atau sudah dipakai data lain (mis. DPL). Nonaktifkan distributor ini sebagai gantinya.',
            fn () => $distributor->regions()->detach(),
        );
    }
}
