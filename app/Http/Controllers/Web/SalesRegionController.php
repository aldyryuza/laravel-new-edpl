<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesZone;
use Illuminate\Contracts\View\View;

class SalesRegionController extends Controller
{
    /**
     * Pilihan zona dikirim dari sini supaya form tetap bisa dipakai tanpa akses ke menu Zona.
     */
    public function index(): View
    {
        return view('pages.sales-regions.index', [
            'zones' => SalesZone::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }
}
