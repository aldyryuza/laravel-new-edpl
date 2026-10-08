<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Services\DistributorMarginOverrideImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DistributorMarginOverrideController extends Controller
{
    /**
     * Kode grup distributor yang sudah ada (saran input). Outlet dicari lewat AJAX (api/dpl/distributor-margin-overrides/outlet-options).
     */
    public function index(): View
    {
        return view('pages.dpl.distributor-margin-overrides.index', [
            'groupCodes' => Distributor::query()->whereNotNull('group_code')->distinct()->orderBy('group_code')->pluck('group_code'),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(DistributorMarginOverrideImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-margin-khusus-outlet.xlsx');
    }
}
