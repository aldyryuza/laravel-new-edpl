<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Contracts\View\View;

class ProductMarginOverrideController extends Controller
{
    /**
     * Pilihan product aktif, team, dan kode grup distributor yang sudah ada (saran input).
     */
    public function index(): View
    {
        return view('pages.dpl.product-margin-overrides.index', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'teams' => Team::query()->orderBy('code')->get(['id', 'code', 'name', 'is_active']),
            'groupCodes' => Distributor::query()->whereNotNull('group_code')->distinct()->orderBy('group_code')->pluck('group_code'),
        ]);
    }
}
