<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\ProductPriceRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\Dpl\ProductPrice;
use App\Services\ProductPriceImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Harga product per region harga (legacy dpl_price_product) dengan periode berlaku.
 */
class ProductPriceController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['product:id,code,name,volume', 'priceRegion:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:product-prices.read', only: ['datatable']),
            new Middleware('can:product-prices.create', only: ['store']),
            new Middleware('can:product-prices.update', only: ['update']),
            new Middleware('can:product-prices.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:product-prices.create', 'can:product-prices.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Filter opsional: price_region_id, is_active, effective_today (berlaku hari ini).
     * Pencarian: kode/nama product.
     */
    public function datatable(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $query = ProductPrice::query()
            ->select(['id', 'product_id', 'price_region_id', 'price', 'regular_price', 'valid_from', 'valid_until', 'is_active'])
            ->with(self::RELATIONS)
            ->when($request->integer('price_region_id'), fn (Builder $query, int $id) => $query->where('price_region_id', $id))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('effective_today'), fn (Builder $query) => $query
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today)));

        return DataTables::eloquent($query)
            ->whitelist(['price', 'regular_price', 'valid_from', 'valid_until', 'is_active'])
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

    public function store(ProductPriceRequest $request): JsonResponse
    {
        $price = ProductPrice::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Harga product berhasil dibuat',
            'data' => $price->load(self::RELATIONS),
        ], 201);
    }

    public function update(ProductPriceRequest $request, ProductPrice $productPrice): JsonResponse
    {
        $productPrice->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Harga product berhasil diperbarui',
            'data' => $productPrice->load(self::RELATIONS),
        ]);
    }

    /**
     * Import Excel (upsert per product + region harga + berlaku mulai). Bila ada baris error, tidak ada
     * yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, ProductPriceImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Harga Product selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Harga belum direferensikan transaksi lain (DPL menyimpan snapshot harga sendiri), jadi aman dihapus.
     * Untuk menjaga histori harga, lebih disarankan menonaktifkan atau mengisi "berlaku sampai".
     */
    public function destroy(ProductPrice $productPrice): JsonResponse
    {
        $productPrice->delete();

        return response()->json(['success' => true, 'message' => 'Harga product berhasil dihapus', 'data' => null]);
    }
}
