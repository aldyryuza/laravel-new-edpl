<?php

namespace App\Exports;

use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalItemState;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Services\Dpl\DplPricing;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel DPL yang sudah approved (legacy printout/excel): header, item, dan status approval per level.
 * Konsolidasi: ditambah daftar outlet (Kontrak, 25% Bed, No. DPL outlet); DPL anak memakai approval DPL induknya.
 * HNA - Disc On & HNA+PPN - Disc On dihitung dari snapshot base_price item, dibulatkan ke rupiah.
 */
class DplProposalExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(private DplProposal $proposal) {}

    public function view(): View
    {
        $proposal = $this->proposal->load([
            'outlet:id,code,name,address',
            'creator:id,nik,name',
            'items.product:id,code,name',
            'items.distributor:id,code,name',
            'items.bonusScheme:id,name',
            'items.terms',
            'consolidation.outlets.outlet:id,code,name,address',
            'consolidation.outlets.generatedDpl:id,dpl_number',
        ]);
        $ppn = (float) (app(DplPricing::class)->ppnPercent() ?? 0);
        $consolidationSource = $proposal->incomingRelations()
            ->where('relation_type', DplRelation::TYPE_CONSOLIDATION_CHILD)
            ->with('source')
            ->first()?->source;

        $instance = ($consolidationSource ?? $proposal)->approvalInstances()
            ->where('status', '!=', ApprovalInstance::STATUS_CANCELLED)
            ->latest('id')
            ->with(['steps.workflowStep.role:id,name', 'steps.itemStates', 'steps.actions.actor:id,name'])
            ->first();

        $steps = ($instance?->steps ?? collect())
            ->sortBy(fn ($step) => $step->workflowStep->sequence)
            ->map(function ($step) {
                $approves = $step->actions->where('action', 'APPROVE');

                return [
                    'role' => $step->workflowStep->role?->name,
                    'status' => match ($step->status) {
                        'COMPLETED' => 'Approved',
                        'REJECTED' => 'Ditolak',
                        default => 'Menunggu',
                    },
                    'approvers' => $approves->pluck('actor.name')->filter()->unique()->implode(', '),
                    'date' => $approves->max('created_at')?->timezone(config('dpl.timezone'))->format('d-m-Y H:i'),
                ];
            })
            ->values();

        $items = $proposal->items->values()->map(function (DplItem $item, int $index) use ($ppn) {
            $terms = $item->terms;
            $discountOn = (float) $terms?->principle_discount_on + (float) $terms?->distributor_discount_on;
            $hna = $terms?->base_price === null ? null : (float) $terms->base_price * (1 - $discountOn / 100);

            return [
                'no' => $index + 1,
                'distributor' => $item->distributor?->name,
                'product' => $item->product?->name,
                'minimum_order' => $item->minimum_order,
                'bonus' => $item->bonusScheme?->name,
                'discount_total' => (float) $terms?->principle_discount_on + (float) $terms?->principle_discount_off
                    + (float) $terms?->distributor_discount_on + (float) $terms?->distributor_discount_off,
                'distributor_on' => (float) $terms?->distributor_discount_on,
                'distributor_off' => (float) $terms?->distributor_discount_off,
                'principle_on' => (float) $terms?->principle_discount_on,
                'principle_off' => (float) $terms?->principle_discount_off,
                'direct' => $item->is_direct_delivery ? 'Yes' : 'No',
                'factory_pickup' => $item->is_factory_pickup ? 'Yes' : 'No',
                'support' => $item->is_support_satoria ? 'Yes' : 'No',
                'support_logistic' => (float) $terms?->logistic_support,
                'logistic_fee' => $terms?->logistic_fee === null ? null : (float) $terms->logistic_fee,
                'calculator' => $terms?->calculator_percentage === null ? null : (float) $terms->calculator_percentage,
                'hna_ppn_disc' => $hna === null ? null : round($hna * (1 + $ppn / 100)),
                'hna_disc' => $hna === null ? null : round($hna),
                'price_type' => $terms?->price_type,
                'margin' => $terms?->margin === null ? null : (float) $terms->margin,
                'remark' => $item->remark,
            ];
        });

        return view('exports.dpl-proposal', [
            'proposal' => $proposal,
            'items' => $items,
            'steps' => $steps,
            'consolidationSource' => $consolidationSource,
            'allApproved' => $instance !== null && ApprovalItemState::query()
                ->whereIn('step_instance_id', $instance->steps->pluck('id'))
                ->where('status', '!=', ApprovalItemState::STATUS_APPROVED)
                ->doesntExist(),
        ]);
    }

    public function title(): string
    {
        return str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $this->proposal->dpl_number);
    }
}
