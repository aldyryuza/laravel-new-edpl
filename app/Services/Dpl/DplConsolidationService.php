<?php

namespace App\Services\Dpl;

use App\Models\Dpl\DplConsolidation;
use App\Models\Dpl\DplConsolidationOutlet;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Models\Dpl\DplStatusHistory;
use App\Models\Outlet;
use App\Models\OutletSalesAssignment;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Konsolidasi / Group RS (legacy is_consolidation, rewrite dari DplController::store + approveAllItem):
 * - DPL induk tipe CONSOLIDATION tanpa outlet, nomor {nomor DPL}-C dari zona pengaju; satu set item untuk semua outlet.
 * - Outlet: Kontrak dan "25% Bed" per outlet (Bed × rasio × hari, manual bila bed 0/kosong).
 * - Approval memakai wilayah pengaju (region -> zona -> global), lihat DplApprovalService.
 * - Semua item approved -> dipecah (sekali, dalam transaksi approval) menjadi satu DPL APPROVED per outlet: nomor dari
 *   zona outlet, item & terms disalin apa adanya, relasi CONSOLIDATION_CHILD. Aktivasi mengikuti aturan DPL biasa.
 * - Pemilik DPL anak: mode MANUAL atau team pengaju di config('dpl.consolidation_creator_owned_teams') = pengaju;
 *   mode GROUP = PIC outlet (SPV, bila kosong AM). Outlet GROUP tanpa PIC tidak bisa diajukan.
 * - DPL induk tidak pernah aktif.
 */
class DplConsolidationService
{
    public function __construct(
        private DplNumberGenerator $numberGenerator,
        private DplDraftService $drafts,
    ) {}

    /**
     * @param  array<string, mixed>  $header  date_start, date_end, description
     * @param  array{mode: string, outlet_group_id: int|null, outlets: array<int, array<string, mixed>>}  $consolidation
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(User $user, string $teamCode, array $header, array $consolidation, array $items): DplProposal
    {
        return DB::transaction(function () use ($user, $teamCode, $header, $consolidation, $items): DplProposal {
            [$region, $zone] = $this->proposerRouting($user);
            // Zona nomor DPL induk: zona pengaju; pengaju Nasional / lintas zona memakai zona outlet pertama.
            $numberZone = $zone ?? Outlet::query()->with('area.region.zone')->findOrFail($consolidation['outlets'][0]['outlet_id'])->area->region->zone;
            $today = now(config('dpl.timezone'));
            $number = $this->numberGenerator->next($numberZone->code, $today).'-C';

            $proposal = DplProposal::query()->create([
                ...$header,
                'dpl_number' => $number,
                'date_offer' => $today->toDateString(),
                'outlet_id' => null,
                'team_code' => $teamCode,
                'created_by_user_id' => $user->id,
                'is_contract' => false,
                'bed_count_snapshot' => null,
                'bed_order_minimum' => null,
                'proposal_type' => DplProposal::TYPE_CONSOLIDATION,
                'proposal_status' => DplProposal::STATUS_DRAFT,
                'program_status' => DplProposal::PROGRAM_INACTIVE,
            ]);

            DplStatusHistory::query()->create([
                'dpl_proposal_id' => $proposal->id,
                'status_type' => DplStatusHistory::TYPE_PROPOSAL,
                'old_status' => null,
                'new_status' => DplProposal::STATUS_DRAFT,
                'changed_by_user_id' => $user->id,
            ]);

            $record = DplConsolidation::query()->create([
                'dpl_proposal_id' => $proposal->id,
                'code' => $number,
                'created_by_user_id' => $user->id,
                'status' => DplConsolidation::STATUS_OPEN,
                'mode' => $consolidation['mode'],
                'outlet_group_id' => $consolidation['mode'] === DplConsolidation::MODE_GROUP ? $consolidation['outlet_group_id'] : null,
                'region_id' => $region?->id,
                'zone_id' => $zone?->id,
            ]);

            $this->syncOutlets($record, $consolidation['outlets']);
            $this->drafts->syncItems($proposal, $items);

            return $proposal;
        });
    }

    /**
     * Mode dan group RS tidak bisa diganti; outlet (tambah/hapus, Kontrak, 25% Bed) dan item bisa.
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $outlets
     * @param  array<int, array<string, mixed>>  $items
     */
    public function update(DplProposal $proposal, array $header, array $outlets, array $items): DplProposal
    {
        return DB::transaction(function () use ($proposal, $header, $outlets, $items): DplProposal {
            $proposal->update([...$header, 'is_contract' => false, 'bed_count_snapshot' => null, 'bed_order_minimum' => null]);
            $this->syncOutlets($proposal->consolidation, $outlets);
            $this->drafts->syncItems($proposal, $items);

            return $proposal;
        });
    }

    /**
     * Wilayah pengaju untuk routing approver: satu region -> region + zonanya; beberapa region dalam satu zona -> zona;
     * Nasional / lintas zona -> tanpa wilayah (approver global).
     *
     * @return array{0: SalesRegion|null, 1: SalesZone|null}
     */
    public function proposerRouting(User $user): array
    {
        $scope = $user->regionScope();

        if ($scope->isNational() || $scope->regionIds() === []) {
            return [null, null];
        }

        $regions = SalesRegion::query()->with('zone')->whereKey($scope->regionIds())->get();

        if ($regions->count() === 1) {
            return [$regions->first(), $regions->first()->zone];
        }

        return $regions->pluck('zone_id')->unique()->count() === 1 ? [null, $regions->first()->zone] : [null, null];
    }

    /**
     * DPL anak disebar ke PIC outlet: mode GROUP dan team pengaju tidak dikecualikan.
     */
    public function ownersFollowPic(DplProposal $proposal): bool
    {
        $creatorOwnedTeams = array_map('mb_strtoupper', config('dpl.consolidation_creator_owned_teams', []));

        return $proposal->consolidation?->mode === DplConsolidation::MODE_GROUP
            && ! in_array(mb_strtoupper($proposal->team_code), $creatorOwnedTeams, true);
    }

    /**
     * Outlet yang belum punya PIC padahal DPL anaknya harus disebar ke PIC (submit ditolak).
     *
     * @return Collection<int, Outlet>
     */
    public function outletsWithoutPic(DplProposal $proposal): Collection
    {
        if (! $this->ownersFollowPic($proposal)) {
            return collect();
        }

        return $proposal->consolidation->outlets()
            ->whereNotIn('outlet_id', OutletSalesAssignment::query()->select('outlet_id'))
            ->with('outlet:id,code,name')
            ->get()
            ->pluck('outlet');
    }

    /**
     * Pecah DPL induk yang sudah approved menjadi DPL per outlet. Dipanggil di dalam transaksi approval;
     * outlet yang sudah punya DPL anak dilewati (aman dipanggil ulang).
     * PIC dihapus setelah diajukan -> DPL anak milik pengaju (dicatat di riwayat status).
     *
     * @return Collection<int, DplProposal> DPL anak yang baru dibuat
     */
    public function generateChildren(DplProposal $parent, ?User $actor): Collection
    {
        $consolidation = DplConsolidation::query()->where('dpl_proposal_id', $parent->id)->lockForUpdate()->firstOrFail();
        $rows = $consolidation->outlets()->whereNull('generated_dpl_id')->with('outlet.area.region.zone')->orderBy('id')->get();
        $pics = $this->ownersFollowPic($parent)
            ? OutletSalesAssignment::query()->whereIn('outlet_id', $rows->pluck('outlet_id'))->get()->keyBy('outlet_id')
            : collect();
        $items = $parent->items()->with('terms')->orderBy('id')->get();
        $today = now(config('dpl.timezone'));
        $children = collect();

        foreach ($rows as $row) {
            /** @var DplConsolidationOutlet $row */
            $picOwner = $pics->get($row->outlet_id)?->ownerUserId();
            $owner = $picOwner ?? $parent->created_by_user_id;

            $child = DplProposal::query()->create([
                'dpl_number' => $this->numberGenerator->next($row->outlet->area->region->zone->code, $today),
                'outlet_id' => $row->outlet_id,
                'team_code' => $parent->team_code,
                'created_by_user_id' => $owner,
                'date_offer' => $parent->date_offer,
                'date_start' => $parent->date_start,
                'date_end' => $parent->date_end,
                'is_contract' => $row->is_contract,
                'bed_count_snapshot' => $row->bed_count_snapshot,
                'bed_order_minimum' => $row->bed_order_minimum,
                'proposal_type' => DplProposal::TYPE_NORMAL,
                'proposal_status' => DplProposal::STATUS_APPROVED,
                'program_status' => DplProposal::PROGRAM_INACTIVE,
                'description' => $parent->description,
                'submitted_at' => $parent->submitted_at,
                'approved_at' => $parent->approved_at ?? now(),
            ]);

            DplStatusHistory::query()->create([
                'dpl_proposal_id' => $child->id,
                'status_type' => DplStatusHistory::TYPE_PROPOSAL,
                'old_status' => null,
                'new_status' => DplProposal::STATUS_APPROVED,
                'reason' => "Dibuat dari konsolidasi {$parent->dpl_number}"
                    .($this->ownersFollowPic($parent) && $picOwner === null ? ' (outlet tanpa PIC: milik pengaju)' : ''),
                'changed_by_user_id' => $actor?->id,
            ]);

            foreach ($items as $item) {
                /** @var DplItem $item */
                $copy = $item->replicate();
                $copy->dpl_proposal_id = $child->id;
                $copy->save();

                if ($item->terms !== null) {
                    $terms = $item->terms->replicate();
                    $terms->dpl_item_id = $copy->id;
                    $terms->save();
                }
            }

            DplRelation::query()->create([
                'source_dpl_id' => $parent->id,
                'target_dpl_id' => $child->id,
                'relation_type' => DplRelation::TYPE_CONSOLIDATION_CHILD,
                'created_by_user_id' => $actor?->id ?? $parent->created_by_user_id,
            ]);

            $row->update(['owner_user_id' => $owner, 'generated_dpl_id' => $child->id]);
            $children->push($child);
        }

        $consolidation->update(['status' => DplConsolidation::STATUS_GENERATED]);

        return $children;
    }

    /**
     * Outlet konsolidasi untuk snapshot revisi / deteksi perubahan (outlet berubah = semua item approval ulang).
     *
     * @return array<int, array{is_contract: bool, bed_order_minimum: string|null}>
     */
    public function outletSnapshot(DplProposal $proposal): array
    {
        return $proposal->consolidation()->first()?->outlets()->orderBy('outlet_id')->get()
            ->mapWithKeys(fn (DplConsolidationOutlet $row) => [$row->outlet_id => [
                'is_contract' => $row->is_contract,
                'bed_order_minimum' => $row->bed_order_minimum,
            ]])->all() ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  outlet_id, is_contract, bed_order_minimum (dipakai bila bed outlet 0/kosong)
     */
    private function syncOutlets(DplConsolidation $consolidation, array $rows): void
    {
        $outletIds = array_map(fn (array $row) => (int) $row['outlet_id'], $rows);
        $bedCounts = Outlet::query()->whereKey($outletIds)->pluck('bed_count', 'id');

        $consolidation->outlets()->whereNotIn('outlet_id', $outletIds)->delete();

        foreach ($rows as $row) {
            $bedCount = $bedCounts[(int) $row['outlet_id']] ?? null;

            $consolidation->outlets()->updateOrCreate(['outlet_id' => (int) $row['outlet_id']], [
                'is_contract' => (bool) ($row['is_contract'] ?? false),
                'bed_count_snapshot' => $bedCount,
                'bed_order_minimum' => $this->drafts->bedOrderMinimum($bedCount, $row['bed_order_minimum'] ?? null),
            ]);
        }
    }
}
