<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OrganizationImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesZoneController extends Controller
{
    public function index(): View
    {
        return view('pages.sales-zones.index');
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk berisi kode referensi).
     */
    public function template(OrganizationImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(OrganizationImportService::ZONE), 'template-import-zona.xlsx');
    }
}
