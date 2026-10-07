<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;

class UserOrgAssignmentController extends Controller
{
    /**
     * Pilihan user, zona, region, area, dan team untuk form.
     */
    public function index(): View
    {
        return view('pages.user-assignments.index', [
            'users' => User::query()->orderBy('name')->get(['id', 'nik', 'name', 'is_active']),
            'zones' => SalesZone::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'regions' => SalesRegion::query()->with('zone:id,name')->orderBy('name')->get(['id', 'code', 'name', 'zone_id', 'is_active']),
            'areas' => SalesArea::query()->with('region:id,name')->orderBy('name')->get(['id', 'code', 'name', 'region_id', 'is_active']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }
}
