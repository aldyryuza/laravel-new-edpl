<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Province;
use Illuminate\Contracts\View\View;

class ApprovalRuleController extends Controller
{
    /**
     * Pilihan step workflow DPL_STANDARD (urut sequence) dan provinsi untuk form.
     */
    public function index(): View
    {
        return view('pages.dpl.approval-rules.index', [
            'steps' => ApprovalWorkflowStep::query()
                ->whereHas('workflow', fn ($query) => $query->where('code', ApprovalWorkflow::CODE_DPL_STANDARD))
                ->with('role:id,name')
                ->orderBy('sequence')
                ->get(['id', 'sequence', 'role_id']),
            'provinces' => Province::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
