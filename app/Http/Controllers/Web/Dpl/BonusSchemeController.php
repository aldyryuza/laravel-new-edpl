<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BonusSchemeController extends Controller
{
    public function index(): View
    {
        return view('pages.dpl.bonus-schemes.index');
    }
}
