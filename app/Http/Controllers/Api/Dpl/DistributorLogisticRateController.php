<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DistributorLogisticRateRequest;
use App\Models\Dpl\DistributorLogisticRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Tarif logistic fee (%) khusus grup distributor + kota (pengganti legacy DPL_UDC_LOGISTIC_PER_AREA).
 */
class DistributorLogisticRateController extends Controller implements HasMiddleware
{
    private const RELATIONS = ['city:id,code,name'];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:distributor-logistic-rates.read', only: ['datatable']),
            new Middleware('can:distributor-logistic-rates.create', only: ['store']),
            new Middleware('can:distributor-logistic-rates.update', only: ['update']),
            new Middleware('can:distributor-logistic-rates.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: kode grup, kode/nama kota.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = DistributorLogisticRate::query()
            ->select(['id', 'distributor_group_code', 'city_id', 'logistic_fee', 'note', 'is_active'])
            ->with(self::RELATIONS);

        return DataTables::eloquent($query)
            ->whitelist(['distributor_group_code', 'logistic_fee', 'is_active'])
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('distributor_group_code', "%{$term}%")
                        ->orWhereHas('city', fn (Builder $city) => $city
                            ->whereLike('code', "%{$term}%")
                            ->orWhereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    public function store(DistributorLogisticRateRequest $request): JsonResponse
    {
        $rate = DistributorLogisticRate::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tarif logistic distributor berhasil dibuat',
            'data' => $rate->load(self::RELATIONS),
        ], 201);
    }

    public function update(DistributorLogisticRateRequest $request, DistributorLogisticRate $distributorLogisticRate): JsonResponse
    {
        $distributorLogisticRate->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tarif logistic distributor berhasil diperbarui',
            'data' => $distributorLogisticRate->load(self::RELATIONS),
        ]);
    }

    /**
     * Tidak direferensikan tabel lain (DPL menyimpan snapshot logistic fee), jadi aman dihapus.
     */
    public function destroy(DistributorLogisticRate $distributorLogisticRate): JsonResponse
    {
        $distributorLogisticRate->delete();

        return response()->json(['success' => true, 'message' => 'Tarif logistic distributor berhasil dihapus', 'data' => null]);
    }
}
