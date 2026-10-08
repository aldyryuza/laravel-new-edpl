<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Distributor;
use App\Models\Product;
use App\Services\DistributorMarginRuleImportService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DistributorMarginRuleController extends Controller
{
    /**
     * Pilihan product aktif, kota, dan kode grup distributor yang sudah ada (saran input).
     */
    public function index(): View
    {
        return view('pages.dpl.distributor-margin-rules.index', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'cities' => City::query()->orderBy('name')->get(['id', 'code', 'name']),
            'groupCodes' => Distributor::query()->whereNotNull('group_code')->distinct()->orderBy('group_code')->pluck('group_code'),
        ]);
    }

    /**
     * Template Excel untuk import (sheet Data + Petunjuk).
     */
    public function template(DistributorMarginRuleImportService $importService): BinaryFileResponse
    {
        return Excel::download($importService->template(), 'template-import-margin-khusus-product.xlsx');
    }
}
