<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Distributor;
use App\Models\SalesRegion;
use App\Services\DistributorImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DistributorController extends Controller
{
    /**
     * Pilihan kota, region, dan induk dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu Region.
     */
    public function index(): View
    {
        return view('pages.distributors.index', [
            'cities' => City::query()->with('province:id,name')->orderBy('name')->get(['id', 'code', 'name', 'province_id', 'is_active']),
            'regions' => SalesRegion::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'parents' => Distributor::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk berisi kode region).
     */
    public function template(DistributorImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-distributor.xlsx');
    }
}
