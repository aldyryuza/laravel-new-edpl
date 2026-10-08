<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OutletSalesAssignmentImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutletSalesAssignmentController extends Controller
{
    /**
     * Outlet dan user dicari lewat AJAX (api/outlet-sales-assignments/outlet-options, user-options).
     */
    public function index(): View
    {
        return view('pages.outlet-sales-assignments.index');
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(OutletSalesAssignmentImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-pic-outlet.xlsx');
    }
}
