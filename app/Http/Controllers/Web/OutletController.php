<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OwnershipType;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Services\OutletImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutletController extends Controller
{
    /**
     * Pilihan filter/form dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu master lain.
     * Pilihan area/region dibatasi wilayah user; import hanya untuk user Nasional.
     */
    public function index(Request $request): View
    {
        $scope = $request->user()->regionScope();

        return view('pages.outlets.index', [
            'canImport' => $scope->isNational(),
            'cities' => City::query()->with('province:id,name')->orderBy('name')->get(['id', 'code', 'name', 'province_id', 'is_active']),
            'regions' => SalesRegion::query()
                ->when(! $scope->isNational(), fn (Builder $query) => $query->whereHas('areas', fn (Builder $area) => $area->whereKey($scope->areaIds() ?: [0])))
                ->orderBy('name')->get(['id', 'code', 'name']),
            'areas' => SalesArea::query()
                ->when(! $scope->isNational(), fn (Builder $query) => $query->whereKey($scope->areaIds() ?: [0]))
                ->with('region:id,name')->orderBy('name')->get(['id', 'code', 'name', 'region_id', 'is_active']),
            'categories' => OutletCategory::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'ownershipTypes' => OwnershipType::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'groups' => OutletGroup::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk berisi kode referensi).
     */
    public function template(OutletImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-outlet.xlsx');
    }
}
