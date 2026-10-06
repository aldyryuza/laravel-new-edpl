<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\CodeMasterImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProvinceController extends Controller
{
    public function index(): View
    {
        return view('pages.provinces.index');
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(CodeMasterImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(CodeMasterImportService::PROVINCE), 'template-import-provinsi.xlsx');
    }
}
