<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesRegion;
use Illuminate\Contracts\View\View;

class SalesAreaController extends Controller
{
    /**
     * Pilihan region dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu Region.
     */
    public function index(): View
    {
        return view('pages.sales-areas.index', [
            'regions' => SalesRegion::query()->with('zone:id,name')->orderBy('name')->get(['id', 'code', 'name', 'zone_id', 'is_active']),
        ]);
    }
}
