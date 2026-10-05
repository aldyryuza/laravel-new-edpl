<?php

namespace App\Http\Controllers\Web;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /**
     * Ekspor daftar user ke Excel sesuai pencarian di tabel. Dicatat ke audit log
     * karena berisi data pribadi (NIK, email).
     */
    public function export(Request $request): BinaryFileResponse
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? null;

        activity(AuditLog::DATA)
            ->event('exported')
            ->withProperties([...AuditLog::requestContext(), 'search' => $search])
            ->log('Ekspor daftar user ke Excel');

        return Excel::download(new UsersExport($search), 'daftar-user-'.now()->format('Ymd-His').'.xlsx');
    }
}
