<?php

namespace App\Http\Controllers\Api\Dpl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dpl\DplProposalRequest;
use App\Models\Distributor;
use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalOverrideRequest;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\Dpl\DplApprovalService;
use App\Services\Dpl\DplCalculator;
use App\Services\Dpl\DplDerivationService;
use App\Services\Dpl\DplDraftService;
use App\Services\Dpl\DplItemDefaults;
use App\Services\Dpl\DplPricing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
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
            new Middleware('can:dpl-proposals.read', only: ['datatable', 'show', 'pricing', 'calculate', 'itemDefaults', 'approval']),
            new Middleware('can:dpl-proposals.approve', only: ['approve', 'reject', 'reviewOverride']),
            new Middleware('can:dpl-proposals.create', only: ['store', 'outletOptions', 'amend', 'renew']),
            new Middleware('can:dpl-proposals.update', only: ['update', 'submit', 'requestOverride']),
            new Middleware('can:dpl-proposals.delete', only: ['destroy']),
        ];
    }

    /**
     * Data tabel (Yajra). Pencarian: nomor DPL, kode/nama outlet. Filter opsional: proposal_status, team_code, mine,
     * awaiting_me (DPL dalam approval di mana user menjadi kandidat approver step yang masih PENDING).
     * Kolom `awaiting_me`: user (berhak approve) punya item yang bisa ditindak sekarang.
     */
    public function datatable(Request $request, DplApprovalService $approvals): JsonResponse
    {
        $user = $request->user();
        $canApprove = $user->can('dpl-proposals.approve');

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
            ->when($request->boolean('mine'), fn (Builder $query) => $query->where('created_by_user_id', $request->user()->id))
            ->when($request->boolean('awaiting_me'), fn (Builder $query) => $query
                ->whereIn('proposal_status', [DplProposal::STATUS_IN_APPROVAL, DplProposal::STATUS_PARTIALLY_APPROVED])
                ->whereHas('approvalInstances', fn (Builder $instance) => $instance
                    ->where('status', ApprovalInstance::STATUS_IN_PROGRESS)
                    ->whereHas('steps', fn (Builder $step) => $step
                        ->where('status', ApprovalStepInstance::STATUS_PENDING)
                        ->whereHas('assignees', fn (Builder $assignee) => $assignee->where('user_id', $user->id)))));

        return DataTables::eloquent($query)
            ->whitelist(['dpl_number', 'date_offer', 'date_start', 'date_end', 'proposal_status', 'created_at'])
            ->addColumn('can_edit', fn (DplProposal $proposal) => $proposal->isEditableDraftOf($request->user()))
            ->addColumn('awaiting_me', fn (DplProposal $proposal) => $canApprove && $approvals->actionableStates($proposal, $user)->isNotEmpty())
            ->addColumn('waiting_roles', fn (DplProposal $proposal) => $approvals->waitingRoleNames($proposal))
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
            ->get(['id', 'code', 'name', 'area_id', 'bed_count']);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $outlets->map(fn (Outlet $outlet) => [
                'id' => $outlet->id,
                'text' => "{$outlet->name} ({$outlet->code})".($outlet->area ? " - {$outlet->area->name}" : ' - tanpa area'),
                'bed_count' => $outlet->bed_count,
            ]),
        ]);
    }

    /**
     * Harga per product per jenis harga yang berlaku pada `date` (Date Start) dan PPN aktif, untuk tampilan
     * HNA - Disc On / HNA+PPN - Disc On.
     */
    public function pricing(Request $request, DplPricing $pricing): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => [
                'ppn_percent' => $pricing->ppnPercent(),
                'default_price_type' => DplPricing::defaultPriceType(),
                'prices' => (object) $pricing->pricesOn($date),
            ],
        ]);
    }

    /**
     * Hitung Total Diskon kalkulator untuk item di form (tanpa menyimpan). Rumus sama persis dengan yang disimpan
     * saat draft disimpan (DplCalculator), supaya angka di form = angka untuk auto reject.
     */
    public function calculate(Request $request, DplCalculator $calculator): JsonResponse
    {
        $percent = ['nullable', 'numeric'];
        $data = $request->validate([
            'team_code' => ['required', 'string', 'max:20'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.principle_discount_on' => $percent,
            'items.*.principle_discount_off' => $percent,
            'items.*.distributor_discount_on' => $percent,
            'items.*.distributor_discount_off' => $percent,
            'items.*.margin' => $percent,
            'items.*.logistic_fee' => $percent,
            'items.*.logistic_support' => $percent,
            'items.*.bonus_scheme_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.is_support_satoria' => ['boolean'],
            'items.*.is_direct_delivery' => ['boolean'],
            'items.*.is_factory_pickup' => ['boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dihitung',
            'data' => $calculator->evaluate($data['items'], $data['team_code']),
        ]);
    }

    /**
     * Nilai awal Margin & Logistic Fee item (DplItemDefaults) untuk mengisi form saat product/distributor/Direct berubah.
     * `product_ids[]` (mode Group) mengembalikan nilai per product: {product_id: {...}}.
     * Distributor dan outlet harus di wilayah user.
     */
    public function itemDefaults(Request $request, DplItemDefaults $defaults): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required_without:product_ids', 'integer'],
            'product_ids' => ['required_without:product_id', 'array', 'max:100'],
            'product_ids.*' => ['integer'],
            'distributor_id' => ['required', 'integer'],
            'outlet_id' => ['nullable', 'integer'],
            'team_code' => ['required', 'string', 'max:20'],
            'is_direct_delivery' => ['boolean'],
            'is_factory_pickup' => ['boolean'],
            'group_mode' => ['boolean'],
        ]);

        $user = $request->user();
        $distributor = Distributor::query()->visibleTo($user)->findOrFail($data['distributor_id']);
        $outlet = isset($data['outlet_id']) ? Outlet::query()->visibleTo($user)->findOrFail($data['outlet_id']) : null;
        $resolve = fn (Product $product) => $defaults->resolve(
            $product,
            $distributor,
            $outlet,
            $data['team_code'],
            $request->boolean('is_direct_delivery'),
            $request->boolean('is_factory_pickup'),
            $request->boolean('group_mode'),
        );

        // Mode Group: nilai per product, dikunci product_id.
        $result = isset($data['product_ids'])
            ? Product::query()->whereKey($data['product_ids'])->get()->mapWithKeys(fn (Product $product) => [$product->id => $resolve($product)])
            : $resolve(Product::query()->findOrFail($data['product_id']));

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => $result,
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

    /**
     * Simpan draft; DPL yang sudah diajukan (diedit pengaju setelah reject, atau RM/NSM selama approval) langsung
     * diajukan ulang: approval item yang tidak berubah dipertahankan (DplApprovalService::resubmit).
     */
    public function update(DplProposalRequest $request, DplProposal $proposal, DplDraftService $draftService, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        abort_unless($approvals->canEdit($proposal, $request->user()), 403, 'Anda tidak bisa mengubah DPL ini.');

        $isDraft = $proposal->proposal_status === DplProposal::STATUS_DRAFT;
        $editorSequence = $isDraft ? null : $approvals->editorSequence($proposal, $request->user());
        $before = $isDraft ? null : $approvals->snapshot($proposal);

        DB::transaction(function () use ($request, $proposal, $draftService, $approvals, $isDraft, $before, $editorSequence): void {
            $draftService->update($proposal, $request->proposalAttributes(), $request->proposalItems());

            if (! $isDraft) {
                $approvals->resubmit($proposal, $request->user(), $before, $editorSequence);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $isDraft ? "Draft DPL {$proposal->dpl_number} berhasil disimpan" : "DPL {$proposal->dpl_number} disimpan dan diajukan ulang",
            'data' => $this->present($request, $proposal->fresh()),
        ]);
    }

    /**
     * Pengaju meminta item yang ditolak otomatis dibuka kembali (alasan wajib).
     */
    public function requestOverride(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $approvals->requestOverride($proposal, $request->user(), $data['item_id'], $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan Open terkirim',
            'data' => $this->present($request, $proposal->fresh()),
        ]);
    }

    /**
     * Reviewer Open (GM) membuka item yang ditolak otomatis (`approve` true) atau menolak permintaannya.
     */
    public function reviewOverride(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'approve' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = $approvals->reviewOverride($proposal, $request->user(), $data['item_ids'], $request->boolean('approve'), $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $request->boolean('approve') ? "{$count} item dibuka kembali" : 'Permintaan Open ditolak',
            'data' => $this->present($request, $proposal->fresh()),
        ]);
    }

    /**
     * Make a Copy (AMENDMENT): draft baru dari DPL aktif milik user, periode hari ini s/d Date End sumber.
     */
    public function amend(Request $request, DplProposal $proposal, DplDerivationService $derivations): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $copy = $derivations->amend($proposal, $request->user());

        return response()->json([
            'success' => true,
            'message' => "Draft amendment {$copy->dpl_number} dibuat dari {$proposal->dpl_number}",
            'data' => ['id' => $copy->id, 'dpl_number' => $copy->dpl_number],
        ], 201);
    }

    /**
     * Duplicate (RENEWAL): draft baru dari DPL approved milik user dengan periode baru; approval dari awal.
     */
    public function renew(Request $request, DplProposal $proposal, DplDerivationService $derivations): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $data = $request->validate([
            'date_start' => ['required', 'date_format:Y-m-d'],
            'date_end' => ['required', 'date_format:Y-m-d'],
        ]);
        $copy = $derivations->renew($proposal, $request->user(), $data['date_start'], $data['date_end']);

        return response()->json([
            'success' => true,
            'message' => "Draft renewal {$copy->dpl_number} dibuat dari {$proposal->dpl_number}",
            'data' => ['id' => $copy->id, 'dpl_number' => $copy->dpl_number],
        ], 201);
    }

    /**
     * Ajukan draft ke approval (hanya pembuat draft). Jalur & approver per item ditentukan server.
     */
    public function submit(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $proposal = $approvals->submit($proposal, $request->user());

        return response()->json([
            'success' => true,
            'message' => $proposal->proposal_status === DplProposal::STATUS_REJECTED
                ? "DPL {$proposal->dpl_number} diajukan, tetapi ada item yang ditolak otomatis (melewati batas kalkulator)."
                : "DPL {$proposal->dpl_number} berhasil diajukan",
            'data' => $this->present($request, $proposal),
        ]);
    }

    /**
     * Status approval per step & item, plus item yang bisa ditindak user sekarang.
     */
    public function approval(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);

        $instance = $proposal->approvalInstances()
            ->latest('id')
            ->with([
                'steps' => fn ($query) => $query->join('dpl.approval_workflow_steps as ws', 'ws.id', '=', 'approval_step_instances.workflow_step_id')
                    ->orderBy('ws.sequence')
                    ->select('approval_step_instances.*'),
                'steps.workflowStep.role:id,name',
                'steps.assigneeUsers:id,nik,name',
                'steps.itemStates',
                'steps.actions' => fn ($query) => $query->latest('id'),
                'steps.actions.actor:id,nik,name',
            ])
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil diambil',
            'data' => [
                'instance' => $instance,
                'actionable_item_ids' => $approvals->actionableStates($proposal, $request->user())->keys()->values(),
            ],
        ]);
    }

    /**
     * Approve item (satu atau banyak / "approve all") pada step yang menjadi giliran user.
     */
    public function approve(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $count = $approvals->approve($proposal, $request->user(), $data['item_ids'], $data['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => "{$count} item berhasil di-approve",
            'data' => $this->present($request, $proposal->fresh()),
        ]);
    }

    /**
     * Tolak item (alasan wajib). DPL menjadi REJECTED sampai diedit dan diajukan ulang.
     */
    public function reject(Request $request, DplProposal $proposal, DplApprovalService $approvals): JsonResponse
    {
        $this->ensureVisible($request, $proposal);
        $data = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $count = $approvals->reject($proposal, $request->user(), $data['item_ids'], $data['reason']);

        return response()->json([
            'success' => true,
            'message' => "{$count} item ditolak",
            'data' => $this->present($request, $proposal->fresh()),
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

    private function canAmend(DplProposal $proposal, User $user): bool
    {
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();

        return $proposal->created_by_user_id === $user->id
            && $proposal->proposal_status === DplProposal::STATUS_APPROVED
            && $proposal->program_status === DplProposal::PROGRAM_ACTIVE
            && $proposal->date_start->toDateString() <= $today
            && $proposal->date_end->toDateString() >= $today;
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

        $user = $request->user();
        $approvals = app(DplApprovalService::class);
        $autoRejected = $approvals->autoRejectedItemIds($proposal);

        return [
            ...$proposal->toArray(),
            'can_edit' => $approvals->canEdit($proposal, $user),
            'waiting_roles' => $approvals->waitingRoleNames($proposal),
            // Item auto reject & aksi Open: pengaju bisa minta, reviewer (GM) bisa membuka.
            'auto_rejected_item_ids' => $autoRejected,
            'pending_open_item_ids' => $proposal->overrideRequests()->where('status', ApprovalOverrideRequest::STATUS_PENDING)->pluck('dpl_item_id')->all(),
            'can_request_open' => $autoRejected !== [] && $proposal->created_by_user_id === $user->id,
            'can_review_open' => $autoRejected !== [] && $approvals->isOverrideReviewer($user),
            // Make a Copy / Duplicate (hanya pemilik DPL approved) dan asal-usul DPL ini.
            'can_amend' => $this->canAmend($proposal, $user),
            'can_renew' => $proposal->created_by_user_id === $user->id && $proposal->proposal_status === DplProposal::STATUS_APPROVED,
            'derived_from' => $proposal->incomingRelations()->with('source:id,dpl_number')->get()
                ->map(fn ($relation) => ['type' => $relation->relation_type, 'id' => $relation->source_dpl_id, 'dpl_number' => $relation->source?->dpl_number])
                ->first(),
            'derivatives' => $proposal->outgoingRelations()->with('target:id,dpl_number,proposal_status')->get()
                ->map(fn ($relation) => [
                    'type' => $relation->relation_type,
                    'id' => $relation->target_dpl_id,
                    'dpl_number' => $relation->target?->dpl_number,
                    'proposal_status' => $relation->target?->proposal_status,
                ])->values(),
        ];
    }
}
