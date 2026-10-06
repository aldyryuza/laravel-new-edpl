<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SalesZoneController extends Controller
{
    public function index(): View
    {
        return view('pages.sales-zones.index');
    }
}
