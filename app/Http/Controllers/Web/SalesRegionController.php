<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesZone;
use App\Services\CodeMasterImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesRegionController extends Controller
{
    /**
     * Pilihan zona dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu Zona.
     */
    public function index(): View
    {
        return view('pages.sales-regions.index', [
            'zones' => SalesZone::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk berisi kode referensi).
     */
    public function template(CodeMasterImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(CodeMasterImportService::REGION), 'template-import-region.xlsx');
    }
}
