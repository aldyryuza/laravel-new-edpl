<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DistributorMarginOverrideRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Distributor;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Outlet;
use App\Services\DistributorMarginOverrideImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Margin khusus outlet per grup distributor (legacy dpl_distributor_spesial_margin_outlet, KODEDIST = grup).
 * Hanya berlaku untuk cabang grup itu yang ditandai is_special_case_margin.
 */
class DistributorMarginOverrideController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['outlet:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:distributor-margin-overrides.read', only: ['datatable', 'outletOptions']),
            new Middleware('can:distributor-margin-overrides.create', only: ['store']),
            new Middleware('can:distributor-margin-overrides.update', only: ['update']),
            new Middleware('can:distributor-margin-overrides.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:distributor-margin-overrides.create', 'can:distributor-margin-overrides.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode grup, kode/nama outlet.
     * `has_special_case_distributor` = ada distributor grup ini yang ditandai margin khusus outlet (untuk peringatan).
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = DistributorMarginOverride::query()
            ->select(['id', 'distributor_group_code', 'outlet_id', 'margin', 'note', 'is_active'])
            ->addSelect(['has_special_case_distributor' => Distributor::query()
                ->selectRaw('count(*) > 0')
                ->whereColumn('distributors.group_code', 'distributor_margin_overrides.distributor_group_code')
                ->where('distributors.is_special_case_margin', true)])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['distributor_group_code', 'margin', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('distributor_group_code', "%{$term}%")
                        ->orWhereHas('outlet', fn (Builder $outlet) => $outlet
                            ->whereLike('code', "%{$term}%")
                            ->orWhereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    /**
     * Pilihan outlet aktif untuk select2 AJAX (maks. 20), cari kode/nama.
     */
    public function outletOptions(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $outlets->map(fn (Outlet $outlet) => ['id' => $outlet->id, 'text' => "{$outlet->name} ({$outlet->code})"]),
        ]);
    }

    public function store(DistributorMarginOverrideRequest $request): JsonResponse
    {
        $override = DistributorMarginOverride::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Margin khusus outlet berhasil dibuat',
            'data' => $override->load(self::RELATIONS),
        ], 201);
    }

    public function update(DistributorMarginOverrideRequest $request, DistributorMarginOverride $distributorMarginOverride): JsonResponse
    {
        $distributorMarginOverride->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Margin khusus outlet berhasil diperbarui',
            'data' => $distributorMarginOverride->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan grup + outlet). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, DistributorMarginOverrideImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Margin Khusus Outlet selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Tidak direferensikan tabel lain (DPL menyimpan snapshot margin), jadi aman dihapus.
     */
    public function destroy(DistributorMarginOverride $distributorMarginOverride): JsonResponse
    {
        $distributorMarginOverride->delete();

        return response()->json(['success' => true, 'message' => 'Margin khusus outlet berhasil dihapus', 'data' => null]);
    }
}
