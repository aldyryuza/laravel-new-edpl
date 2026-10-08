<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DistributorMarginRuleRequest;
use App\Models\Dpl\DistributorMarginRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Margin khusus product per grup distributor (legacy dpl_distributor_margin). Kota kosong = semua kota.
 */
class DistributorMarginRuleController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['product:id,code,name', 'city:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:distributor-margin-rules.read', only: ['datatable']),
            new Middleware('can:distributor-margin-rules.create', only: ['store']),
            new Middleware('can:distributor-margin-rules.update', only: ['update']),
            new Middleware('can:distributor-margin-rules.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode grup, kode/nama product.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = DistributorMarginRule::query()
            ->select(['id', 'distributor_group_code', 'product_id', 'city_id', 'margin', 'note', 'is_active'])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['distributor_group_code', 'margin', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('distributor_group_code', "%{$term}%")
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->whereLike('code', "%{$term}%")
                            ->orWhereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    public function store(DistributorMarginRuleRequest $request): JsonResponse
    {
        $rule = DistributorMarginRule::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Margin khusus product berhasil dibuat',
            'data' => $rule->load(self::RELATIONS),
        ], 201);
    }

    public function update(DistributorMarginRuleRequest $request, DistributorMarginRule $distributorMarginRule): JsonResponse
    {
        $distributorMarginRule->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Margin khusus product berhasil diperbarui',
            'data' => $distributorMarginRule->load(self::RELATIONS),
        ]);
    }

    /**
     * Tidak direferensikan tabel lain (DPL menyimpan snapshot margin), jadi aman dihapus.
     */
    public function destroy(DistributorMarginRule $distributorMarginRule): JsonResponse
    {
        $distributorMarginRule->delete();

        return response()->json(['success' => true, 'message' => 'Margin khusus product berhasil dihapus', 'data' => null]);
    }
}
