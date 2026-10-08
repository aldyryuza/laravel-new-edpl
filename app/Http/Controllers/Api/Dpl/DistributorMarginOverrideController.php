<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DistributorMarginOverrideRequest;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Margin khusus outlet per distributor (legacy dpl_distributor_spesial_margin_outlet).
 * Hanya berlaku bila distributor ditandai is_special_case_margin.
 */
class DistributorMarginOverrideController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['distributor:id,code,name,is_special_case_margin', 'outlet:id,code,name'];

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
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode/nama distributor atau outlet.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = DistributorMarginOverride::query()
            ->select(['id', 'distributor_id', 'outlet_id', 'margin', 'note', 'is_active'])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['margin', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $match = fn (Builder $related) => $related->whereLike('code', "%{$term}%")->orWhereLike('name', "%{$term}%");

                    $query->where(fn (Builder $query) => $query->whereHas('distributor', $match)->orWhereHas('outlet', $match));
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
     * Tidak direferensikan tabel lain (DPL menyimpan snapshot margin), jadi aman dihapus.
     */
    public function destroy(DistributorMarginOverride $distributorMarginOverride): JsonResponse
    {
        $distributorMarginOverride->delete();

        return response()->json(['success' => true, 'message' => 'Margin khusus outlet berhasil dihapus', 'data' => null]);
    }
}
