<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Contracts\View\View;

class DistributorMarginOverrideController extends Controller
{
    /**
     * Pilihan distributor. Outlet dicari lewat AJAX (api/dpl/distributor-margin-overrides/outlet-options).
     */
    public function index(): View
    {
        return view('pages.dpl.distributor-margin-overrides.index', [
            'distributors' => Distributor::query()->orderBy('name')->get(['id', 'code', 'name', 'is_special_case_margin', 'is_active']),
        ]);
    }
}
