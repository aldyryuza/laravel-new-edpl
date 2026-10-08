<?php

namespace App\Services\Dpl;

use App\Models\Dpl\ApprovalAction;
use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalItemState;
use App\Models\Dpl\ApprovalOverrideRequest;
use App\Models\Dpl\ApprovalRule;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Dpl\ApproverAssignment;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplStatusHistory;
use App\Models\Outlet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur submit & approval DPL per item (pengganti legacy DplController::createApprovals / approveItem / rejectItem).
 *
 * - Jalur item: aturan approval pertama yang cocok (Total Calculator + provinsi kota outlet), selain itu
 *   semua step workflow DPL_STANDARD (RM -> NSM -> GM -> SMD -> DMD).
 * - Approver: approver_assignments role step, paling spesifik dulu: region outlet, zona outlet, global.
 *   Beberapa approver satu step = siapa saja boleh; aksi pertama yang dihitung.
 * - Urutan: step bisa bertindak setelah semua step gerbang (is_gate) sebelumnya di jalur item approve.
 * - Auto reject saat submit bila Total Calculator melewati batas (kecuali DPL Clearance).
 * - Satu item ditolak -> DPL REJECTED, approval berhenti sampai diedit & disubmit ulang.
 * - Semua item approve -> DPL APPROVED; program ACTIVE bila periode sudah berjalan (DPL aktif lama
 *   outlet + team yang sama -> SUPERSEDED), selain itu diaktifkan job harian (refreshProgramStatuses).
 *
 * Semua pengecekan hak akses ada di server (legacy hanya di UI).
 */
class DplApprovalService
{
    public function __construct(private DplCalculator $calculator) {}

    /**
     * Ajukan draft: buat instance approval, jalur & approver per item, lalu auto reject.
     *
     * @throws ValidationException
     */
    public function submit(DplProposal $proposal, User $user): DplProposal
    {
        return DB::transaction(function () use ($proposal, $user): DplProposal {
            $proposal = DplProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if (! $proposal->isEditableDraftOf($user)) {
                throw ValidationException::withMessages(['proposal' => 'Hanya draft milik Anda yang bisa diajukan.']);
            }

            $proposal->load(['outlet.area.region', 'outlet.city', 'items.terms']);
            $this->startInstance($proposal, $user);

            $this->changeProposalStatus($proposal, DplProposal::STATUS_IN_APPROVAL, $user, 'Diajukan');
            $proposal->update(['submitted_at' => now()]);
            $this->refreshStatuses($proposal->fresh(), $user);

            return $proposal->fresh();
        });
    }

    /**
     * Approve item-item pada step yang sedang menjadi giliran user.
     *
     * @param  array<int, int>  $itemIds
     * @return int jumlah item yang di-approve
     *
     * @throws ValidationException
     */
    public function approve(DplProposal $proposal, User $user, array $itemIds, ?string $reason = null): int
    {
        return $this->act($proposal, $user, $itemIds, ApprovalAction::APPROVE, $reason);
    }

    /**
     * Tolak item; alasan wajib. DPL menjadi REJECTED dan approval berhenti.
     *
     * @param  array<int, int>  $itemIds
     *
     * @throws ValidationException
     */
    public function reject(DplProposal $proposal, User $user, array $itemIds, string $reason): int
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan reject wajib diisi.']);
        }

        return $this->act($proposal, $user, $itemIds, ApprovalAction::REJECT, $reason);
    }

    /**
     * Item state yang bisa ditindak user sekarang, dikunci dpl_item_id (satu step per item: giliran paling awal).
     *
     * @return Collection<int, ApprovalItemState>
     */
    public function actionableStates(DplProposal $proposal, User $user): Collection
    {
        if (! in_array($proposal->proposal_status, [DplProposal::STATUS_IN_APPROVAL, DplProposal::STATUS_PARTIALLY_APPROVED], true)) {
            return collect();
        }

        $instance = $this->currentInstance($proposal);

        if ($instance === null) {
            return collect();
        }

        $states = $this->statesOf($instance);
        $userStepIds = $instance->steps()
            ->whereHas('assignees', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id')
            ->all();

        return $states
            ->filter(fn (ApprovalItemState $state) => $state->status === ApprovalItemState::STATUS_PENDING
                && in_array($state->step_instance_id, $userStepIds, true)
                && $this->gatesPassed($state, $states))
            ->sortBy(fn (ApprovalItemState $state) => $state->stepInstance->workflowStep->sequence)
            ->unique('dpl_item_id')
            ->keyBy('dpl_item_id');
    }

    /**
     * Apakah user boleh mengubah DPL: draft milik sendiri; DPL REJECTED milik sendiri (pengaju);
     * atau approver step can_edit (RM/NSM) selama DPL dalam approval / REJECTED.
     */
    public function canEdit(DplProposal $proposal, User $user): bool
    {
        return $proposal->isEditableDraftOf($user) || $this->editorSequence($proposal, $user) !== null;
    }

    /**
     * Level pengedit DPL yang sudah diajukan: 0 = pengaju (approval ulang dari awal), selain itu sequence step
     * can_edit yang ditugaskan ke user (approval di bawah level ini dipertahankan). Null = tidak boleh edit.
     */
    public function editorSequence(DplProposal $proposal, User $user): ?int
    {
        $status = $proposal->proposal_status;

        if (! in_array($status, [DplProposal::STATUS_IN_APPROVAL, DplProposal::STATUS_PARTIALLY_APPROVED, DplProposal::STATUS_REJECTED], true)) {
            return null;
        }

        if ($status === DplProposal::STATUS_REJECTED && $proposal->created_by_user_id === $user->id) {
            return 0;
        }

        $instance = $this->currentInstance($proposal);

        return $instance?->steps()
            ->whereHas('workflowStep', fn ($query) => $query->where('can_edit', true))
            ->whereHas('assignees', fn ($query) => $query->where('user_id', $user->id))
            ->with('workflowStep')
            ->get()
            ->min(fn (ApprovalStepInstance $step) => $step->workflowStep->sequence);
    }

    /**
     * Snapshot data bisnis DPL (header + item + terms) untuk revisi dan deteksi perubahan.
     *
     * @return array{header: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function snapshot(DplProposal $proposal): array
    {
        $proposal = $proposal->fresh(['items.terms']);
        $header = $proposal->only(['proposal_type', 'date_start', 'date_end', 'is_contract', 'bed_order_minimum', 'description']);
        $header['date_start'] = $proposal->date_start?->toDateString();
        $header['date_end'] = $proposal->date_end?->toDateString();

        return [
            'header' => $header,
            'items' => $proposal->items->mapWithKeys(fn (DplItem $item) => [$item->id => [
                ...$item->only([...DplDraftService::ITEM_FIELDS]),
                'terms' => $item->terms?->only([...DplDraftService::TERM_FIELDS, 'base_price']),
            ]])->all(),
        ];
    }

    /**
     * Ajukan ulang setelah DPL yang sudah diajukan diedit: simpan revisi, batalkan instance lama, buat instance baru.
     * Item yang tidak berubah (dan tidak ditolak) mempertahankan status approval. Item baru/berubah/ditolak mulai
     * lagi dari level pengedit: approval step di bawah level pengedit dipertahankan, sisanya menunggu; auto reject
     * dicek ulang. Header berubah = semua item dianggap berubah.
     *
     * @param  array{header: array<string, mixed>, items: array<int, array<string, mixed>>}  $before  snapshot() sebelum edit
     */
    public function resubmit(DplProposal $proposal, User $user, array $before, int $editorSequence): DplProposal
    {
        return DB::transaction(function () use ($proposal, $user, $before, $editorSequence): DplProposal {
            $proposal = DplProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $after = $this->snapshot($proposal);
            $oldInstance = $this->currentInstance($proposal);
            $oldStates = $oldInstance === null ? collect() : $this->statesOf($oldInstance);
            $headerChanged = $before['header'] != $after['header'];

            $proposal->revisions()->create([
                'revision_number' => (int) $proposal->revisions()->max('revision_number') + 1,
                'reason' => "Diedit oleh {$user->name}",
                'snapshot' => $before,
                'created_by_user_id' => $user->id,
            ]);

            $changed = fn (DplItem $item) => $headerChanged
                || ! isset($before['items'][$item->id])
                || $before['items'][$item->id] != ($after['items'][$item->id] ?? null)
                || $oldStates->where('dpl_item_id', $item->id)->contains('status', ApprovalItemState::STATUS_REJECTED);

            $oldInstance?->update(['status' => ApprovalInstance::STATUS_CANCELLED, 'completed_at' => now()]);

            $proposal->load(['outlet.area.region', 'outlet.city', 'items.terms']);

            $this->startInstance(
                $proposal,
                $user,
                function (DplItem $item, ApprovalWorkflowStep $step) use ($oldStates, $changed, $editorSequence): ?ApprovalItemState {
                    $old = $oldStates->first(fn (ApprovalItemState $state) => $state->dpl_item_id === $item->id
                        && $state->stepInstance->workflow_step_id === $step->id);
                    $keep = $old !== null && $old->status === ApprovalItemState::STATUS_APPROVED
                        && (! $changed($item) || $step->sequence < $editorSequence);

                    return $keep ? $old : null;
                },
                $changed,
            );

            $this->changeProposalStatus($proposal, DplProposal::STATUS_IN_APPROVAL, $user, 'Diajukan ulang setelah diedit');
            $proposal->update(['submitted_at' => now()]);
            $this->refreshStatuses($proposal->fresh(), $user);

            return $proposal->fresh();
        });
    }

    /**
     * Pengaju meminta item yang ditolak otomatis (batas kalkulator) dibuka kembali.
     *
     * @throws ValidationException
     */
    public function requestOverride(DplProposal $proposal, User $user, int $itemId, string $reason): ApprovalOverrideRequest
    {
        if ($proposal->created_by_user_id !== $user->id) {
            throw ValidationException::withMessages(['proposal' => 'Hanya pengaju yang bisa meminta Open.']);
        }

        if (! in_array($itemId, $this->autoRejectedItemIds($proposal), true)) {
            throw ValidationException::withMessages(['item_id' => 'Item tidak sedang ditolak otomatis.']);
        }

        if ($proposal->overrideRequests()->where('dpl_item_id', $itemId)->where('status', ApprovalOverrideRequest::STATUS_PENDING)->exists()) {
            throw ValidationException::withMessages(['item_id' => 'Permintaan Open untuk item ini masih menunggu.']);
        }

        return $proposal->overrideRequests()->create([
            'dpl_item_id' => $itemId,
            'requested_by_user_id' => $user->id,
            'request_reason' => $reason,
            'status' => ApprovalOverrideRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    /**
     * Reviewer Open (role config dpl.override_reviewer_role, default GM) membuka item yang ditolak otomatis:
     * state kembali menunggu, permintaan Open terkait disetujui. Dengan $approve = false hanya menolak permintaan.
     *
     * @param  array<int, int>  $itemIds
     *
     * @throws ValidationException
     */
    public function reviewOverride(DplProposal $proposal, User $reviewer, array $itemIds, bool $approve, ?string $reason = null): int
    {
        if (! $this->isOverrideReviewer($reviewer)) {
            throw ValidationException::withMessages(['proposal' => 'Hanya '.config('dpl.override_reviewer_role').' yang bisa membuka item yang ditolak otomatis.']);
        }

        return DB::transaction(function () use ($proposal, $reviewer, $itemIds, $approve, $reason): int {
            $proposal = DplProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
            $autoRejected = $this->autoRejectedItemIds($proposal);

            if ($itemIds === [] || array_diff($itemIds, $autoRejected) !== []) {
                throw ValidationException::withMessages(['items' => 'Ada item yang tidak sedang ditolak otomatis.']);
            }

            $proposal->overrideRequests()
                ->whereIn('dpl_item_id', $itemIds)
                ->where('status', ApprovalOverrideRequest::STATUS_PENDING)
                ->update([
                    'status' => $approve ? ApprovalOverrideRequest::STATUS_APPROVED : ApprovalOverrideRequest::STATUS_REJECTED,
                    'reviewed_by_user_id' => $reviewer->id,
                    'review_reason' => $reason,
                    'reviewed_at' => now(),
                ]);

            if (! $approve) {
                return count($itemIds);
            }

            $instance = $this->currentInstance($proposal);

            foreach ($this->statesOf($instance)->whereIn('dpl_item_id', $itemIds) as $state) {
                $state->update(['status' => ApprovalItemState::STATUS_PENDING, 'rejected_at' => null, 'reject_reason' => null]);
                ApprovalAction::query()->create([
                    'step_instance_id' => $state->step_instance_id,
                    'dpl_item_id' => $state->dpl_item_id,
                    'actor_user_id' => $reviewer->id,
                    'action' => ApprovalAction::OVERRIDE,
                    'reason' => $reason,
                ]);
            }

            $this->refreshStatuses($proposal, $reviewer);

            return count($itemIds);
        });
    }

    /**
     * Nama role step yang masih menunggu (urut sequence) untuk DPL dalam approval, mis. ['NSM', 'GM', 'SMD', 'DMD'].
     *
     * @return array<int, string>
     */
    public function waitingRoleNames(DplProposal $proposal): array
    {
        if (! in_array($proposal->proposal_status, [DplProposal::STATUS_IN_APPROVAL, DplProposal::STATUS_PARTIALLY_APPROVED], true)) {
            return [];
        }

        $instance = $this->currentInstance($proposal);

        return $instance === null ? [] : $instance->steps()
            ->where('status', ApprovalStepInstance::STATUS_PENDING)
            ->with('workflowStep.role:id,name')
            ->get()
            ->sortBy(fn (ApprovalStepInstance $step) => $step->workflowStep->sequence)
            ->map(fn (ApprovalStepInstance $step) => $step->workflowStep->role?->name ?? "Step {$step->workflowStep->sequence}")
            ->values()
            ->all();
    }

    /**
     * User terdaftar sebagai approver aktif (Approver DPL) hari ini.
     */
    public function isApprover(User $user): bool
    {
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();

        return ApproverAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->exists();
    }

    public function isOverrideReviewer(User $user): bool
    {
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();

        return ApproverAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', config('dpl.override_reviewer_role')))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->exists();
    }

    /**
     * Item yang saat ini ditolak otomatis (state REJECTED dengan aksi AUTO_REJECT terakhir) di instance aktif.
     *
     * @return array<int, int>
     */
    public function autoRejectedItemIds(DplProposal $proposal): array
    {
        $instance = $this->currentInstance($proposal);

        if ($instance === null || $proposal->proposal_status !== DplProposal::STATUS_REJECTED) {
            return [];
        }

        $rejected = $this->statesOf($instance)->where('status', ApprovalItemState::STATUS_REJECTED)->pluck('dpl_item_id')->unique();

        return ApprovalAction::query()
            ->whereIn('step_instance_id', $instance->steps->pluck('id'))
            ->whereIn('dpl_item_id', $rejected)
            ->orderByDesc('id')
            ->get(['dpl_item_id', 'action'])
            ->unique('dpl_item_id')
            ->where('action', ApprovalAction::AUTO_REJECT)
            ->pluck('dpl_item_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Aktifkan DPL approved yang periodenya sudah mulai dan expire DPL aktif yang sudah lewat Date End.
     * Dijalankan job harian (dpl:refresh-program-status) dan setelah approval terakhir.
     *
     * @return array{activated: int, expired: int}
     */
    public function refreshProgramStatuses(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now(config('dpl.timezone'));
        $result = ['activated' => 0, 'expired' => 0];

        DplProposal::query()
            ->where('program_status', DplProposal::PROGRAM_ACTIVE)
            ->whereDate('date_end', '<', $today->toDateString())
            ->each(function (DplProposal $proposal) use (&$result): void {
                $this->changeProgramStatus($proposal, DplProposal::PROGRAM_EXPIRED, null, 'Periode program berakhir');
                $result['expired']++;
            });

        DplProposal::query()
            ->where('proposal_status', DplProposal::STATUS_APPROVED)
            ->where('program_status', DplProposal::PROGRAM_INACTIVE)
            ->whereDate('date_start', '<=', $today->toDateString())
            ->orderBy('approved_at')
            ->each(function (DplProposal $proposal) use ($today, &$result): void {
                if (DB::transaction(fn () => $this->activateIfDue($proposal, $today))) {
                    $result['activated']++;
                }
            });

        return $result;
    }

    /**
     * @param  array<int, int>  $itemIds
     */
    private function act(DplProposal $proposal, User $user, array $itemIds, string $action, ?string $reason): int
    {
        return DB::transaction(function () use ($proposal, $user, $itemIds, $action, $reason): int {
            $proposal = DplProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            $actionable = $this->actionableStates($proposal, $user);
            $requested = array_values(array_unique(array_map('intval', $itemIds)));
            $notAllowed = array_diff($requested, $actionable->keys()->all());

            if ($requested === [] || $notAllowed !== []) {
                throw ValidationException::withMessages([
                    'items' => 'Ada item yang bukan giliran Anda, sudah diproses, atau DPL sedang tidak dalam approval.',
                ]);
            }

            $now = now();

            foreach ($requested as $itemId) {
                /** @var ApprovalItemState $state */
                $state = $actionable[$itemId];
                $approve = $action === ApprovalAction::APPROVE;

                $state->update([
                    'status' => $approve ? ApprovalItemState::STATUS_APPROVED : ApprovalItemState::STATUS_REJECTED,
                    'approved_at' => $approve ? $now : null,
                    'rejected_at' => $approve ? null : $now,
                    'reject_reason' => $approve ? null : $reason,
                ]);

                ApprovalAction::query()->create([
                    'step_instance_id' => $state->step_instance_id,
                    'dpl_item_id' => $itemId,
                    'actor_user_id' => $user->id,
                    'action' => $action,
                    'reason' => $reason,
                ]);
            }

            $this->refreshStatuses($proposal, $user);

            return count($requested);
        });
    }

    /**
     * Buat instance approval baru: jalur & approver per item, state awal, auto reject.
     *
     * @param  (callable(DplItem, ApprovalWorkflowStep): ?ApprovalItemState)|null  $carry  state lama yang dipertahankan (approve)
     * @param  (callable(DplItem): bool)|null  $recheck  item yang dicek ulang auto reject (default semua)
     */
    private function startInstance(DplProposal $proposal, User $user, ?callable $carry = null, ?callable $recheck = null): ApprovalInstance
    {
        if ($proposal->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'DPL belum memiliki item.']);
        }

        $workflow = ApprovalWorkflow::query()
            ->where('code', ApprovalWorkflow::CODE_DPL_STANDARD)
            ->where('is_active', true)
            ->with(['steps' => fn ($query) => $query->orderBy('sequence'), 'steps.role'])
            ->firstOrFail();

        $calculations = $this->calculations($proposal);
        $routes = $this->routes($proposal, $workflow, $calculations);
        $steps = $workflow->steps->whereIn('id', $routes->flatten()->unique()->all())->values();
        $assignees = $this->resolveAssignees($steps, $proposal->outlet);

        $now = now();
        $instance = ApprovalInstance::query()->create([
            'dpl_proposal_id' => $proposal->id,
            'workflow_id' => $workflow->id,
            'status' => ApprovalInstance::STATUS_IN_PROGRESS,
            'started_at' => $now,
        ]);

        /** @var array<int, ApprovalStepInstance> $stepInstances workflow_step_id => instance */
        $stepInstances = [];

        foreach ($steps as $step) {
            $stepInstance = ApprovalStepInstance::query()->create([
                'approval_instance_id' => $instance->id,
                'workflow_step_id' => $step->id,
                'status' => ApprovalStepInstance::STATUS_PENDING,
                'started_at' => $now,
            ]);
            $stepInstance->assigneeUsers()->attach($assignees[$step->id]);
            $stepInstances[$step->id] = $stepInstance;
        }

        foreach ($proposal->items->values() as $index => $item) {
            $calculation = $calculations[$index];
            $autoReject = $calculation['exceeds_limit'] && $proposal->proposal_type !== DplProposal::TYPE_CLEARANCE
                && ($recheck === null || $recheck($item));
            $reason = $autoReject ? 'Total discount is more than '.$this->formatPercent($calculation['limit']).'%' : null;

            foreach ($routes[$item->id] as $stepId) {
                $kept = $autoReject || $carry === null ? null : $carry($item, $steps->firstWhere('id', $stepId));

                ApprovalItemState::query()->create([
                    'step_instance_id' => $stepInstances[$stepId]->id,
                    'dpl_item_id' => $item->id,
                    'status' => match (true) {
                        $autoReject => ApprovalItemState::STATUS_REJECTED,
                        $kept !== null => ApprovalItemState::STATUS_APPROVED,
                        default => ApprovalItemState::STATUS_PENDING,
                    },
                    'approved_at' => $kept?->approved_at,
                    'rejected_at' => $autoReject ? $now : null,
                    'reject_reason' => $reason,
                ]);

                if ($autoReject) {
                    ApprovalAction::query()->create([
                        'step_instance_id' => $stepInstances[$stepId]->id,
                        'dpl_item_id' => $item->id,
                        'actor_user_id' => $user->id,
                        'action' => ApprovalAction::AUTO_REJECT,
                        'reason' => $reason,
                        'metadata' => ['total_discount' => $calculation['total_discount'], 'limit' => $calculation['limit']],
                    ]);
                }
            }
        }

        return $instance;
    }

    /**
     * Perbarui status step, instance, proposal; aktifkan program bila semua item approve.
     */
    private function refreshStatuses(DplProposal $proposal, ?User $user): void
    {
        $instance = $this->currentInstance($proposal);

        if ($instance === null) {
            return;
        }

        $states = $this->statesOf($instance);

        foreach ($instance->steps as $step) {
            $stepStates = $states->where('step_instance_id', $step->id);
            $status = match (true) {
                $stepStates->contains('status', ApprovalItemState::STATUS_REJECTED) => ApprovalStepInstance::STATUS_REJECTED,
                $stepStates->every(fn (ApprovalItemState $state) => $state->status === ApprovalItemState::STATUS_APPROVED) => ApprovalStepInstance::STATUS_COMPLETED,
                default => ApprovalStepInstance::STATUS_PENDING,
            };

            if ($step->status !== $status) {
                $step->update(['status' => $status, 'completed_at' => $status === ApprovalStepInstance::STATUS_PENDING ? null : now()]);
            }
        }

        $allApproved = $states->every(fn (ApprovalItemState $state) => $state->status === ApprovalItemState::STATUS_APPROVED);
        [$proposalStatus, $instanceStatus] = match (true) {
            $states->contains('status', ApprovalItemState::STATUS_REJECTED) => [DplProposal::STATUS_REJECTED, ApprovalInstance::STATUS_REJECTED],
            $allApproved => [DplProposal::STATUS_APPROVED, ApprovalInstance::STATUS_APPROVED],
            $states->contains('status', ApprovalItemState::STATUS_APPROVED) => [DplProposal::STATUS_PARTIALLY_APPROVED, ApprovalInstance::STATUS_IN_PROGRESS],
            default => [DplProposal::STATUS_IN_APPROVAL, ApprovalInstance::STATUS_IN_PROGRESS],
        };

        if ($instance->status !== $instanceStatus) {
            $instance->update([
                'status' => $instanceStatus,
                'completed_at' => $instanceStatus === ApprovalInstance::STATUS_IN_PROGRESS ? null : now(),
            ]);
        }

        if ($proposal->proposal_status !== $proposalStatus) {
            $this->changeProposalStatus($proposal, $proposalStatus, $user, null);
        }

        if ($proposalStatus === DplProposal::STATUS_APPROVED) {
            $proposal->update(['approved_at' => $proposal->approved_at ?? now()]);
            $this->activateIfDue($proposal->fresh(), CarbonImmutable::now(config('dpl.timezone')));
        }
    }

    /**
     * Aktifkan program bila sudah approved dan periode berjalan; DPL aktif lama outlet + team sama -> SUPERSEDED.
     */
    private function activateIfDue(DplProposal $proposal, CarbonImmutable $today): bool
    {
        if ($proposal->proposal_status !== DplProposal::STATUS_APPROVED || $proposal->program_status !== DplProposal::PROGRAM_INACTIVE
            || $proposal->date_start->toDateString() > $today->toDateString()) {
            return false;
        }

        if ($proposal->date_end->toDateString() < $today->toDateString()) {
            $this->changeProgramStatus($proposal, DplProposal::PROGRAM_EXPIRED, null, 'Disetujui setelah periode berakhir');

            return false;
        }

        DplProposal::query()
            ->where('outlet_id', $proposal->outlet_id)
            ->where('team_code', $proposal->team_code)
            ->where('program_status', DplProposal::PROGRAM_ACTIVE)
            ->whereKeyNot($proposal->id)
            ->lockForUpdate()
            ->each(fn (DplProposal $old) => $this->changeProgramStatus($old, DplProposal::PROGRAM_SUPERSEDED, null, "Digantikan DPL {$proposal->dpl_number}"));

        $this->changeProgramStatus($proposal, DplProposal::PROGRAM_ACTIVE, null, 'Program aktif');

        return true;
    }

    /**
     * Hasil kalkulator per item (urutan = $proposal->items), dihitung ulang dari data tersimpan.
     *
     * @return array<int, array{total_discount: string, limit: string|null, exceeds_limit: bool}>
     */
    private function calculations(DplProposal $proposal): array
    {
        $items = $proposal->items->map(fn (DplItem $item) => [
            ...($item->terms?->only(DplDraftService::TERM_FIELDS) ?? []),
            'product_id' => $item->product_id,
            'bonus_scheme_id' => $item->bonus_scheme_id,
            'is_support_satoria' => $item->is_support_satoria,
            'is_direct_delivery' => $item->is_direct_delivery,
            'is_factory_pickup' => $item->is_factory_pickup,
        ])->all();

        return $this->calculator->evaluate($items, $proposal->team_code);
    }

    /**
     * Jalur step per item: aturan approval pertama yang cocok, selain itu semua step wajib workflow.
     *
     * @param  array<int, array{total_discount: string, limit: string|null, exceeds_limit: bool}>  $calculations
     * @return Collection<int, array<int, int>> dpl_item_id => [workflow_step_id] urut sequence
     */
    private function routes(DplProposal $proposal, ApprovalWorkflow $workflow, array $calculations): Collection
    {
        $provinceId = $proposal->outlet->city?->province_id;
        $rules = ApprovalRule::query()->where('is_active', true)->with(['steps', 'provinces'])->orderBy('sort_order')->orderBy('id')->get();
        $defaultRoute = $workflow->steps->where('is_required', true)->pluck('id')->all();

        return $proposal->items->values()->mapWithKeys(function (DplItem $item, int $index) use ($rules, $provinceId, $calculations, $workflow, $defaultRoute) {
            $rule = $rules->first(fn (ApprovalRule $rule) => $rule->coversTotal($calculations[$index]['total_discount'])
                && ($rule->provinces->isEmpty() || $rule->provinces->contains('id', $provinceId)));
            $route = $rule === null
                ? $defaultRoute
                : $workflow->steps->whereIn('id', $rule->steps->pluck('id'))->pluck('id')->all();

            return [$item->id => $route === [] ? $defaultRoute : $route];
        });
    }

    /**
     * Approver per step dari approver_assignments yang berlaku hari ini: region outlet -> zona outlet -> global.
     *
     * @param  Collection<int, ApprovalWorkflowStep>  $steps
     * @return array<int, array<int, int>> workflow_step_id => [user_id]
     *
     * @throws ValidationException
     */
    private function resolveAssignees(Collection $steps, Outlet $outlet): array
    {
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();
        $regionId = $outlet->area?->region_id;
        $zoneId = $outlet->area?->region?->zone_id;
        $result = [];
        $missing = [];

        foreach ($steps as $step) {
            $assignments = ApproverAssignment::query()
                ->where('role_id', $step->role_id)
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->whereHas('user', fn ($query) => $query->where('is_active', true))
                ->get(['user_id', 'region_id', 'zone_id']);

            $byRegion = $regionId === null ? collect() : $assignments->where('region_id', $regionId);
            $byZone = $zoneId === null ? collect() : $assignments->where('zone_id', $zoneId);
            $global = $assignments->whereNull('region_id')->whereNull('zone_id');
            $users = ($byRegion->isNotEmpty() ? $byRegion : ($byZone->isNotEmpty() ? $byZone : $global))->pluck('user_id')->unique()->values()->all();

            if ($users === []) {
                $missing[] = $step->role?->name ?? "step {$step->sequence}";
            }

            $result[$step->id] = $users;
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'approvers' => 'Approver belum diatur untuk wilayah outlet ini: '.implode(', ', $missing).'. Atur di halaman Approver DPL.',
            ]);
        }

        return $result;
    }

    /**
     * Step gerbang (is_gate) sebelumnya di jalur item yang sama harus sudah approve.
     *
     * @param  Collection<int, ApprovalItemState>  $states
     */
    private function gatesPassed(ApprovalItemState $state, Collection $states): bool
    {
        $sequence = $state->stepInstance->workflowStep->sequence;

        return $states
            ->where('dpl_item_id', $state->dpl_item_id)
            ->filter(fn (ApprovalItemState $other) => $other->stepInstance->workflowStep->is_gate
                && $other->stepInstance->workflowStep->sequence < $sequence)
            ->every(fn (ApprovalItemState $other) => $other->status === ApprovalItemState::STATUS_APPROVED);
    }

    private function currentInstance(DplProposal $proposal): ?ApprovalInstance
    {
        return $proposal->approvalInstances()
            ->where('status', '!=', ApprovalInstance::STATUS_CANCELLED)
            ->latest('id')
            ->with('steps')
            ->first();
    }

    /**
     * @return Collection<int, ApprovalItemState>
     */
    private function statesOf(ApprovalInstance $instance): Collection
    {
        return ApprovalItemState::query()
            ->whereIn('step_instance_id', $instance->steps->pluck('id'))
            ->with('stepInstance.workflowStep')
            ->get();
    }

    private function changeProposalStatus(DplProposal $proposal, string $status, ?User $user, ?string $reason): void
    {
        DplStatusHistory::query()->create([
            'dpl_proposal_id' => $proposal->id,
            'status_type' => DplStatusHistory::TYPE_PROPOSAL,
            'old_status' => $proposal->proposal_status,
            'new_status' => $status,
            'reason' => $reason,
            'changed_by_user_id' => $user?->id,
        ]);
        $proposal->update(['proposal_status' => $status]);
    }

    private function changeProgramStatus(DplProposal $proposal, string $status, ?User $user, ?string $reason): void
    {
        DplStatusHistory::query()->create([
            'dpl_proposal_id' => $proposal->id,
            'status_type' => DplStatusHistory::TYPE_PROGRAM,
            'old_status' => $proposal->program_status,
            'new_status' => $status,
            'reason' => $reason,
            'changed_by_user_id' => $user?->id,
        ]);
        $proposal->update(['program_status' => $status]);
    }

    private function formatPercent(?string $value): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
