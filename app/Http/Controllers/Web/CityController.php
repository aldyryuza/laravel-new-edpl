<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Services\CityLogisticFeeImportService;
use App\Services\CodeMasterImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CityController extends Controller
{
    /**
     * Pilihan provinsi dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu Provinsi.
     */
    public function index(): View
    {
        return view('pages.cities.index', [
            'provinces' => Province::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk berisi kode provinsi).
     */
    public function template(CodeMasterImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(CodeMasterImportService::CITY), 'template-import-kota.xlsx');
    }

    /**
     * Template Excel untuk import logistic fee kota (sheet Data + Petunjuk).
     */
    public function logisticFeeTemplate(CityLogisticFeeImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-logistic-fee-kota.xlsx');
    }
}
