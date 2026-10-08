<?php

namespace App\Http\Controllers\Web\Dpl;

use App\Exports\DplProposalExport;
use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\DplProposal;
use App\Models\Product;
use App\Services\Dpl\CommercialParameterReader;
use App\Services\Dpl\DplApprovalService;
use App\Services\Dpl\DplPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DplProposalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pages.dpl.proposals.index', [
            'teams' => $user->effectiveTeams(),
            // Approver (RM–DMD) langsung melihat DPL yang menunggu approval-nya.
            'defaultAwaitingMe' => $user->can('dpl-proposals.approve') && app(DplApprovalService::class)->isApprover($user),
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
     * Export Excel DPL yang sudah approved semua (hak akses dpl-proposals.print).
     */
    public function export(Request $request, DplProposal $proposal): BinaryFileResponse
    {
        abort_unless(DplProposal::query()->visibleTo($request->user())->whereKey($proposal->getKey())->exists(), 404);
        abort_unless($proposal->proposal_status === DplProposal::STATUS_APPROVED, 403, 'Export Excel hanya untuk DPL yang sudah approved semua.');

        return Excel::download(new DplProposalExport($proposal), "DPL-{$proposal->dpl_number}.xlsx");
    }

    /**
     * Pilihan form: product aktif per group, skema bonus aktif, distributor aktif di wilayah user, team user.
     */
    private function form(Request $request, ?DplProposal $proposal): View
    {
        $user = $request->user();
        $parameters = app(CommercialParameterReader::class);

        return view('pages.dpl.proposals.form', [
            'proposal' => $proposal,
            'today' => now(config('dpl.timezone'))->toDateString(),
            // Rumus "25% Bed" untuk tampilan; nilai resmi dihitung DplDraftService saat simpan.
            'bedOrderRatio' => $parameters->number('BED_ORDER_RATIO'),
            'bedOrderDays' => $parameters->number('BED_ORDER_DAYS'),
            // Default jenis harga ditaruh pertama.
            'priceTypes' => array_values(array_unique([DplPricing::defaultPriceType(), ...array_keys(DplPricing::priceTypes())])),
            'teams' => $user->effectiveTeams(),
            'productGroups' => Product::dplGroups(),
            'productOptions' => $this->productOptions(),
            'distributors' => Distributor::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'bonusSchemes' => BonusScheme::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'types' => [DplProposal::TYPE_NORMAL => 'Normal', DplProposal::TYPE_CLEARANCE => 'Clearance (ED dekat)'],
        ]);
    }

    /**
     * Product aktif yang masuk group DPL (product tanpa group tidak bisa dipilih).
     *
     * @return array<int, array{id: int, label: string, group: string, volume: float|null}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'volume', 'product_group'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => "{$product->name} ({$product->code})",
                'group' => $product->dplGroup(),
                'volume' => $product->volume === null ? null : (float) $product->volume,
            ])
            ->filter(fn (array $option) => $option['group'] !== null)
            ->values()
            ->all();
    }
}
