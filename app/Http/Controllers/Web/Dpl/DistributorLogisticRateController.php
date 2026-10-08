<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Distributor;
use Illuminate\Contracts\View\View;

class DistributorLogisticRateController extends Controller
{
    /**
     * Pilihan kota dan kode grup distributor yang sudah ada (saran input).
     */
    public function index(): View
    {
        return view('pages.dpl.distributor-logistic-rates.index', [
            'cities' => City::query()->orderBy('name')->get(['id', 'code', 'name']),
            'groupCodes' => Distributor::query()->whereNotNull('group_code')->distinct()->orderBy('group_code')->pluck('group_code'),
        ]);
    }
}
