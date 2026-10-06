<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Concerns\DeletesRestrictedModels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\BonusSchemeRequest;
use App\Models\Dpl\BonusScheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Skema bonus (legacy dpl_bonus), mis. beli 5 bonus 1.
 */
class BonusSchemeController extends Controller implements HasMiddleware
{
    use DeletesRestrictedModels;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:bonus-schemes.read', only: ['datatable']),
            new Middleware('can:bonus-schemes.create', only: ['store']),
            new Middleware('can:bonus-schemes.update', only: ['update']),
            new Middleware('can:bonus-schemes.delete', only: ['destroy']),
        ];
    }

    public function datatable(): JsonResponse
    {
        $query = BonusScheme::query()
            ->select(['id', 'code', 'name', 'buy_quantity', 'bonus_quantity', 'discount_invoice', 'discount_bonus', 'effective_discount', 'is_active'])
            ->withCount('dplItems');

        return DataTables::eloquent($query)
            ->whitelist(['code', 'name', 'buy_quantity', 'bonus_quantity', 'is_active'])
            ->toJson();
    }

    public function store(BonusSchemeRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Skema bonus berhasil dibuat',
            'data' => BonusScheme::query()->create($request->validated()),
        ], 201);
    }

    public function update(BonusSchemeRequest $request, BonusScheme $bonusScheme): JsonResponse
    {
        $bonusScheme->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Skema bonus berhasil diperbarui',
            'data' => $bonusScheme,
        ]);
    }

    public function destroy(BonusScheme $bonusScheme): JsonResponse
    {
        return $this->deleteOrReject(
            $bonusScheme,
            'Skema bonus berhasil dihapus',
            'Skema bonus sudah dipakai item DPL. Nonaktifkan skema ini sebagai gantinya.',
        );
    }
}
