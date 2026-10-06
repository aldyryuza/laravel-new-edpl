<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Role;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Illuminate\Contracts\View\View;

class ApproverAssignmentController extends Controller
{
    /**
     * Pilihan role (hanya role pada workflow approval), region, zona, dan user aktif untuk form.
     */
    public function index(): View
    {
        return view('pages.dpl.approver-assignments.index', [
            'roles' => Role::query()
                ->whereIn('id', ApprovalWorkflowStep::query()->select('role_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'regions' => SalesRegion::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'zones' => SalesZone::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'nik', 'name']),
        ]);
    }
}
