<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Contoh halaman: Web Controller hanya merender view,
 * data diambil lewat AJAX ke API Controller.
 */
class ExampleController extends Controller
{
    public function index(): View
    {
        return view('pages.example.index');
    }
}
