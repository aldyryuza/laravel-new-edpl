<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Daftar user online. Datanya dari presence channel `online` (Reverb),
 * bukan dari database, jadi tidak ada query berkala.
 */
class OnlineUserController extends Controller
{
    public function index(): View
    {
        return view('pages.online-users.index');
    }
}
