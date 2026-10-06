<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Halaman kategori outlet (GRUP1) dan jenis kepemilikan (GRUP2).
 */
class OutletClassificationController extends Controller
{
    public function index(): View
    {
        return view('pages.outlet-classifications.index');
    }
}
