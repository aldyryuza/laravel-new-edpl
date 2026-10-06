<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\ProductPriceOverrideRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Dpl\ProductPriceOverride;
use App\Services\ProductPriceOverrideImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Harga khusus (legacy dpl_price_product_special): JKN/PADMA, dll., opsional per group RS.
 */
class ProductPriceOverrideController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['product:id,code,name', 'outletGroup:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:special-prices.read', only: ['datatable']),
            new Middleware('can:special-prices.create', only: ['store']),
            new Middleware('can:special-prices.update', only: ['update']),
            new Middleware('can:special-prices.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:special-prices.create', 'can:special-prices.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Filter opsional: price_type, outlet_group_id, is_active, effective_today.
     * Pencarian: kode/nama product.
     */
    public function datatable(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $query = ProductPriceOverride::query()
            ->select(['id', 'price_type', 'outlet_group_id', 'product_id', 'price', 'valid_from', 'valid_until', 'is_active', 'note'])
            ->with(self::RELATIONS)
            ->when($request->filled('price_type'), fn (Builder $query) => $query->where('price_type', $request->input('price_type')))
            ->when($request->integer('outlet_group_id'), fn (Builder $query, int $id) => $query->where('outlet_group_id', $id))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('effective_today'), fn (Builder $query) => $query
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today)));

        return DataTables::eloquent($query)
            ->whitelist(['price_type', 'price', 'valid_from', 'valid_until', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->whereHas('product', fn (Builder $product) => $product
                        ->whereLike('code', "%{$term}%")
                        ->orWhereLike('name', "%{$term}%"));
                }
            })
            ->toJson();
    }

    public function store(ProductPriceOverrideRequest $request): JsonResponse
    {
        $override = ProductPriceOverride::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Harga khusus berhasil dibuat',
            'data' => $override->load(self::RELATIONS),
        ], 201);
    }

    public function update(ProductPriceOverrideRequest $request, ProductPriceOverride $specialPrice): JsonResponse
    {
        $specialPrice->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Harga khusus berhasil diperbarui',
            'data' => $specialPrice->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel. Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, ProductPriceOverrideImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Harga Khusus selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Belum direferensikan transaksi lain (DPL menyimpan snapshot harga), jadi aman dihapus.
     */
    public function destroy(ProductPriceOverride $specialPrice): JsonResponse
    {
        $specialPrice->delete();

        return response()->json(['success' => true, 'message' => 'Harga khusus berhasil dihapus', 'data' => null]);
    }
}
