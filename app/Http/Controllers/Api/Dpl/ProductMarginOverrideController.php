<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\ProductMarginOverrideRequest;
use App\Models\Dpl\ProductMarginOverride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Override margin per product (legacy DPL_PRODUCT_MARGIN_OVERRIDE), hanya item non-Direct.
 * Grup distributor / team kosong = semua.
 */
class ProductMarginOverrideController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['product:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:product-margin-overrides.read', only: ['datatable']),
            new Middleware('can:product-margin-overrides.create', only: ['store']),
            new Middleware('can:product-margin-overrides.update', only: ['update']),
            new Middleware('can:product-margin-overrides.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode/nama product, kode grup, kode team.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = ProductMarginOverride::query()
            ->select(['id', 'product_id', 'distributor_group_code', 'team_code', 'margin', 'note', 'is_active'])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['distributor_group_code', 'team_code', 'margin', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('distributor_group_code', "%{$term}%")
                        ->orWhereLike('team_code', "%{$term}%")
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->whereLike('code', "%{$term}%")
                            ->orWhereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    public function store(ProductMarginOverrideRequest $request): JsonResponse
    {
        $override = ProductMarginOverride::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Override margin product berhasil dibuat',
            'data' => $override->load(self::RELATIONS),
        ], 201);
    }

    public function update(ProductMarginOverrideRequest $request, ProductMarginOverride $productMarginOverride): JsonResponse
    {
        $productMarginOverride->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Override margin product berhasil diperbarui',
            'data' => $productMarginOverride->load(self::RELATIONS),
        ]);
    }

    /**
     * Tidak direferensikan tabel lain (DPL menyimpan snapshot margin), jadi aman dihapus.
     */
    public function destroy(ProductMarginOverride $productMarginOverride): JsonResponse
    {
        $productMarginOverride->delete();

        return response()->json(['success' => true, 'message' => 'Override margin product berhasil dihapus', 'data' => null]);
    }
}
