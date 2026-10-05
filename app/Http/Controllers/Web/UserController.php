<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Contracts\View\View;

class UserController extends Controller
{
    /**
     * Daftar role dikirim dari sini (bukan lewat API roles) supaya form user
     * tetap bisa dipakai oleh user yang tidak punya akses ke menu Role.
     */
    public function index(): View
    {
        return view('pages.users.index', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
