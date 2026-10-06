<?php

namespace Tests\Feature;

use App\Models\ApprovalAction;
use App\Models\ApprovalInstance;
use App\Models\ApprovalItemState;
use App\Models\ApprovalOverrideRequest;
use App\Models\ApprovalStepInstance;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ApproverAssignment;
use App\Models\Attachment;
use App\Models\BonusScheme;
use App\Models\CommercialParameter;
use App\Models\Distributor;
use App\Models\DistributorBranchHead;
use App\Models\DplConsolidation;
use App\Models\DplConsolidationOutlet;
use App\Models\DplItem;
use App\Models\DplItemTerm;
use App\Models\DplProposal;
use App\Models\DplRelation;
use App\Models\DplRevision;
use App\Models\DplStatusHistory;
use App\Models\ExitLetter;
use App\Models\Outlet;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OutletGroupMember;
use App\Models\OwnershipType;
use App\Models\PriceRegion;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductPriceOverride;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\UserAreaScope;
use App\Models\UserOrgAssignment;
use App\Models\UserVisibilityEdge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Memastikan setiap model E-DPL menunjuk ke tabel/schema yang benar dan factory-nya
 * menghasilkan data yang lolos FK/constraint PostgreSQL.
 */
class EdplModelFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function models(): array
    {
        $models = [
            SalesZone::class => 'sales_zones',
            SalesRegion::class => 'sales_regions',
            SalesArea::class => 'sales_areas',
            UserOrgAssignment::class => 'user_org_assignments',
            UserAreaScope::class => 'user_area_scopes',
            UserVisibilityEdge::class => 'user_visibility_edges',
            OutletCategory::class => 'outlet_categories',
            OwnershipType::class => 'ownership_types',
            OutletGroup::class => 'outlet_groups',
            Outlet::class => 'outlets',
            OutletGroupMember::class => 'outlet_group_members',
            Product::class => 'products',
            Distributor::class => 'distributors',
            PriceRegion::class => 'dpl.price_regions',
            ProductPrice::class => 'dpl.product_prices',
            ProductPriceOverride::class => 'dpl.product_price_overrides',
            BonusScheme::class => 'dpl.bonus_schemes',
            CommercialParameter::class => 'dpl.commercial_parameters',
            DplProposal::class => 'dpl.dpl_proposals',
            DplItem::class => 'dpl.dpl_items',
            DplItemTerm::class => 'dpl.dpl_item_terms',
            DplStatusHistory::class => 'dpl.dpl_status_histories',
            DplRevision::class => 'dpl.dpl_revisions',
            DplRelation::class => 'dpl.dpl_relations',
            DplConsolidation::class => 'dpl.dpl_consolidations',
            DplConsolidationOutlet::class => 'dpl.dpl_consolidation_outlets',
            ApprovalWorkflow::class => 'dpl.approval_workflows',
            ApprovalWorkflowStep::class => 'dpl.approval_workflow_steps',
            ApproverAssignment::class => 'dpl.approver_assignments',
            ApprovalInstance::class => 'dpl.approval_instances',
            ApprovalStepInstance::class => 'dpl.approval_step_instances',
            ApprovalItemState::class => 'dpl.approval_item_states',
            ApprovalAction::class => 'dpl.approval_actions',
            ApprovalOverrideRequest::class => 'dpl.approval_override_requests',
            DistributorBranchHead::class => 'dpl.distributor_branch_heads',
            ExitLetter::class => 'dpl.exit_letters',
            Attachment::class => 'dpl.attachments',
        ];

        $cases = [];

        foreach ($models as $class => $table) {
            $cases[class_basename($class)] = [$class, $table];
        }

        return $cases;
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('models')]
    public function test_factory_persists_model_in_expected_table(string $class, string $table): void
    {
        $model = $class::factory()->create();

        $this->assertSame($table, $model->getTable());
        $this->assertDatabaseHas($table, ['id' => $model->getKey()]);
    }

    public function test_proposal_relations_resolve_across_public_and_dpl_schema(): void
    {
        $term = DplItemTerm::factory()->create();
        $proposal = $term->item->proposal;

        $this->assertTrue($proposal->outlet->is(Outlet::query()->find($proposal->outlet_id)));
        $this->assertCount(1, $proposal->items);
        $this->assertSame('10.0000', $proposal->items->first()->terms->principle_discount_on);
    }
}
