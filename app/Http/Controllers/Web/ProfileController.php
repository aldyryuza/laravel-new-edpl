<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Profil milik user yang sedang login; tidak memerlukan hak akses menu.
     */
    public function edit(Request $request): View
    {
        return view('pages.profile.edit', ['user' => $request->user()]);
    }
}
