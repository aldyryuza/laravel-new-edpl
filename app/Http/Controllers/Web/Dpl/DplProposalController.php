<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\DplProposal;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DplProposalController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.dpl.proposals.index', [
            'teams' => $request->user()->effectiveTeams(),
            'statuses' => [
                DplProposal::STATUS_DRAFT, DplProposal::STATUS_SUBMITTED, DplProposal::STATUS_IN_APPROVAL,
                DplProposal::STATUS_PARTIALLY_APPROVED, DplProposal::STATUS_APPROVED, DplProposal::STATUS_REJECTED, DplProposal::STATUS_CANCELLED,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, null);
    }

    /**
     * Detail DPL; form bisa diubah hanya bila draft milik user (data dimuat via api/dpl/proposals/{id}).
     */
    public function show(Request $request, DplProposal $proposal): View
    {
        abort_unless(DplProposal::query()->visibleTo($request->user())->whereKey($proposal->getKey())->exists(), 404);

        return $this->form($request, $proposal);
    }

    /**
     * Pilihan form: product & skema bonus aktif, distributor aktif di wilayah user, team user.
     */
    private function form(Request $request, ?DplProposal $proposal): View
    {
        $user = $request->user();

        return view('pages.dpl.proposals.form', [
            'proposal' => $proposal,
            'today' => now(config('dpl.timezone'))->toDateString(),
            'teams' => $user->effectiveTeams(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'volume']),
            'distributors' => Distributor::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'bonusSchemes' => BonusScheme::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'types' => [DplProposal::TYPE_NORMAL => 'Normal', DplProposal::TYPE_CLEARANCE => 'Clearance (ED dekat)'],
        ]);
    }
}
