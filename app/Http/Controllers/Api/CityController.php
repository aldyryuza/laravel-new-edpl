<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\CityRequest;
use App\Http\Requests\ExcelImportRequest;
use App\Models\City;
use App\Services\CityLogisticFeeImportService;
use App\Services\CodeMasterImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

class CityController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:cities.read', only: ['datatable']),
            new Middleware('can:cities.create', only: ['store']),
            new Middleware('can:cities.update', only: ['update']),
            new Middleware('can:cities.delete', only: ['destroy']),
            // Import bisa membuat dan memperbarui data sekaligus.
            new Middleware(['can:cities.create', 'can:cities.update'], only: ['import']),
            // Import logistic fee hanya memperbarui kota yang sudah ada.
            new Middleware('can:cities.update', only: ['importLogisticFee']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian juga mencakup nama provinsi; filter opsional `province_id`.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = City::query()
            ->select(['cities.id', 'cities.code', 'cities.name', 'cities.province_id', 'cities.logistic_fee', 'cities.is_active'])
            ->with('province:id,code,name')
            ->when($request->integer('province_id'), fn (Builder $query, int $provinceId) => $query->where('province_id', $provinceId));

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('cities.code', "%{$term}%")
                        ->orWhereLike('cities.name', "%{$term}%")
                        ->orWhereHas('province', fn (Builder $province) => $province->whereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    public function store(CityRequest $request): JsonResponse
    {
        $city = City::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kota berhasil dibuat',
            'data' => $city->load('province:id,code,name'),
        ], 201);
    }

    public function update(CityRequest $request, City $city): JsonResponse
    {
        $city->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Kota berhasil diperbarui',
            'data' => $city->load('province:id,code,name'),
        ]);
    }

    /**
     * Import Excel (upsert berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function import(ExcelImportRequest $request, CodeMasterImportService $importService): JsonResponse
    {
        $result = $importService->import(CodeMasterImportService::CITY, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Kota selesai: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    /**
     * Import Excel logistic fee (update-only berdasarkan kode). Bila ada baris error, tidak ada yang disimpan (422, error di `errors.file`).
     */
    public function importLogisticFee(ExcelImportRequest $request, CityLogisticFeeImportService $importService): JsonResponse
    {
        $result = $importService->import($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => "Import Logistic Fee Kota selesai: {$result['updated']} diperbarui, {$result['unchanged']} tidak berubah.",
            'data' => $result,
        ]);
    }

    public function destroy(City $city): JsonResponse
    {
        return $this->deleteOrReject(
            $city,
            'Kota berhasil dihapus',
            'Kota masih dipakai outlet atau distributor. Nonaktifkan kota ini sebagai gantinya.',
        );
    }
}
