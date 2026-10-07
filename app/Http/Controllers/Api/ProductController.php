<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcelImportRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class ProductController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:products.read', only: ['datatable', 'show']),
            new Middleware('can:products.create', only: ['store']),
            new Middleware('can:products.update', only: ['update']),
            new Middleware('can:products.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:products.create', 'can:products.update'], only: ['import']),
        ];
    }

    /**
     * Data tabel (Yajra). Filter opsional: volume, is_branded, is_active.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = Product::query()
            ->select(['products.id', 'products.code', 'products.name', 'products.volume', 'products.product_group', 'products.is_branded', 'products.netsuite_id', 'products.is_active'])
            ->when($request->filled('volume'), fn (Builder $query) => $query->where('volume', $request->input('volume')))
            ->when($request->filled('is_branded'), fn (Builder $query) => $query->where('is_branded', $request->boolean('is_branded')))
            ->when($request->filled('is_active'), fn (Builder $query) => $query->where('is_active', $request->boolean('is_active')));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'volume', 'is_branded', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('products.code', "%{$term}%")
                        ->orWhereLike('products.name', "%{$term}%")
                        ->orWhereLike('products.netsuite_id', "%{$term}%"));
                }
            })
            ->toJson();
    }

    public function store(ProductRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product berhasil dibuat',
            'data' => Product::query()->create($request->validated()),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $product,
        ]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil diperbarui',
            'data' => $product,
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, ProductImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Product selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        return $this->deleteOrReject(
            $product,
            'Product berhasil dihapus',
            'Product sudah dipakai (harga atau DPL). Nonaktifkan product ini sebagai gantinya.',
        );
    }
}
