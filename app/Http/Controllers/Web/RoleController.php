<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Contracts\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('pages.roles.index');
    }

    /**
     * Halaman pengaturan hak akses menu untuk satu role.
     */
    public function access(Role $role): View
    {
        return view('pages.roles.access', ['role' => $role]);
    }
}
