<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Services\CodeMasterImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PriceRegionController extends Controller
{
    public function index(): View
    {
        return view('pages.dpl.price-regions.index');
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(CodeMasterImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(CodeMasterImportService::PRICE_REGION), 'template-import-region-harga.xlsx');
    }
}
