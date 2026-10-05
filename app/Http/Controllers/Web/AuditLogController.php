<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\AuditLog;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('pages.audit-log.index', ['categories' => AuditLog::CATEGORIES]);
    }
}
