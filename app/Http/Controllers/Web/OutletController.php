<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OwnershipType;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Services\OutletImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutletController extends Controller
{
    /**
     * Pilihan filter/form dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu master lain.
     */
    public function index(): View
    {
        return view('pages.outlets.index', [
            'regions' => SalesRegion::query()->orderBy('name')->get(['id', 'code', 'name']),
            'areas' => SalesArea::query()->with('region:id,name')->orderBy('name')->get(['id', 'code', 'name', 'region_id', 'is_active']),
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
