<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DplProposalRequest;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Services\Dpl\DplDraftService;
use App\Services\Dpl\DplPricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Yajra\DataTables\Facades\DataTables;

/**
 * Pengajuan DPL. Tahap ini: draft (buat, ubah, hapus) dan daftar; submit/approval menyusul.
 * DPL terlihat bila outlet-nya di wilayah user; draft hanya bisa diubah/dihapus pembuatnya.
 */
class DplProposalController extends Controller implements HasMiddleware
{
    /** Relasi untuk detail/form. */
    private const DETAIL_RELATIONS = [
        'outlet:id,code,name,area_id,bed_count',
        'outlet.area:id,code,name,region_id',
        'outlet.area.region:id,code,name,zone_id',
        'outlet.area.region.zone:id,code,name',
        'creator:id,nik,name',
        'items.product:id,code,name,volume',
        'items.distributor:id,code,name',
        'items.bonusScheme:id,code,name',
        'items.terms',
    ];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:dpl-proposals.read', only: ['datatable', 'show', 'pricing']),
            new Middleware('can:dpl-proposals.create', only: ['store', 'outletOptions']),
            new Middleware('can:dpl-proposals.update', only: ['update']),
            new Middleware('can:dpl-proposals.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: nomor DPL, kode/nama outlet. Filter opsional: proposal_status, team_code, mine.
     */
    public function datatable(Request $request): JsonResponse
    {
        $query = DplProposal::query()
            ->visibleTo($request->user())
            ->select([
                'dpl_proposals.id', 'dpl_proposals.dpl_number', 'dpl_proposals.outlet_id', 'dpl_proposals.team_code',
                'dpl_proposals.created_by_user_id', 'dpl_proposals.date_offer', 'dpl_proposals.date_start', 'dpl_proposals.date_end',
                'dpl_proposals.proposal_type', 'dpl_proposals.proposal_status', 'dpl_proposals.program_status', 'dpl_proposals.created_at',
            ])
            ->with(['outlet:id,code,name', 'creator:id,nik,name'])
            ->withCount('items')
            ->when($request->filled('proposal_status'), fn (Builder $query) => $query->where('proposal_status', $request->input('proposal_status')))
            ->when($request->filled('team_code'), fn (Builder $query) => $query->where('team_code', $request->input('team_code')))
            ->when($request->boolean('mine'), fn (Builder $query) => $query->where('created_by_user_id', $request->user()->id));

        return DataTables::eloquent($query)
            ->whitelist(['dpl_number', 'date_offer', 'date_start', 'date_end', 'proposal_status', 'created_at'])
            ->addColumn('can_edit', fn (DplProposal $proposal) => $proposal->isEditableDraftOf($request->user()))
            ->filter(function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('search.value'));

                if ($term !== '') {
                    $query->where(fn (Builder $query) => $query
                        ->whereLike('dpl_proposals.dpl_number', "%{$term}%")
                        ->orWhereHas('outlet', fn (Builder $outlet) => $outlet->whereLike('code', "%{$term}%")->orWhereLike('name', "%{$term}%")));
                }
            })
            ->toJson();
    }

    /**
     * Pilihan outlet untuk select2 (outlet aktif di wilayah user, maks. 20 hasil).
     */
    public function outletOptions(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        $outlets = Outlet::query()
            ->visibleTo($request->user())
            ->where('is_active', true)
            ->with('area:id,name')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('code', "%{$term}%")
                ->orWhereLike('name', "%{$term}%")))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name', 'area_id']);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $outlets->map(fn (Outlet $outlet) => [
                'id' => $outlet->id,
                'text' => "{$outlet->name} ({$outlet->code})".($outlet->area ? " - {$outlet->area->name}" : ' - tanpa area'),
            ]),
        ]);
    }

    /**
     * HNA per product yang berlaku pada `date` (Date Start) dan PPN aktif, untuk tampilan HNA - Disc On / HNA+PPN - Disc On.
     */
    public function pricing(Request $request, DplPricing $pricing): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => ['ppn_percent' => $pricing->ppnPercent(), 'hna' => (object) $pricing->hnaOn($date)],
        ]);
    }

    public function store(DplProposalRequest $request, DplDraftService $draftService): JsonResponse
    {
        $proposal = $draftService->create($request->user(), $request->outlet(), $request->teamCode(), $request->proposalAttributes(), $request->proposalItems());

        return response()->json([
            'success' => true,
            'message' => "Draft DPL {$proposal->dpl_number} berhasil dibuat",
            'data' => $this->present($request, $proposal),
        ], 201);
    }

    public function show(Request $request, DplProposal $proposal): JsonResponse
    {
        $this->ensureVisible($request, $proposal);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $this->present($request, $proposal),
        ]);
    }

    public function update(DplProposalRequest $request, DplProposal $proposal, DplDraftService $draftService): JsonResponse
    {
        $this->ensureEditable($request, $proposal);
        $draftService->update($proposal, $request->proposalAttributes(), $request->proposalItems());

        return response()->json([
            'success' => true,
            'message' => "Draft DPL {$proposal->dpl_number} berhasil disimpan",
            'data' => $this->present($request, $proposal),
        ]);
    }

    public function destroy(Request $request, DplProposal $proposal, DplDraftService $draftService): JsonResponse
    {
        $this->ensureEditable($request, $proposal);
        $draftService->delete($proposal);

        return response()->json([
            'success' => true,
            'message' => "Draft DPL {$proposal->dpl_number} berhasil dihapus",
            'data' => null,
        ]);
    }

    /**
     * DPL di luar wilayah user diperlakukan seperti tidak ada (404).
     */
    private function ensureVisible(Request $request, DplProposal $proposal): void
    {
        abort_unless(DplProposal::query()->visibleTo($request->user())->whereKey($proposal->getKey())->exists(), 404);
    }

    private function ensureEditable(Request $request, DplProposal $proposal): void
    {
        $this->ensureVisible($request, $proposal);
        abort_unless($proposal->isEditableDraftOf($request->user()), 403, 'Hanya draft milik Anda yang bisa diubah atau dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Request $request, DplProposal $proposal): array
    {
        $proposal->load(self::DETAIL_RELATIONS);

        return [...$proposal->toArray(), 'can_edit' => $proposal->isEditableDraftOf($request->user())];
    }
}
