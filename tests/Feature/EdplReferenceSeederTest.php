<?php

namespace Tests\Feature;

use App\Models\Dpl\ApprovalWorkflow;
use App\Models\OutletCategory;
use App\Models\OwnershipType;
use App\Models\Role;
use Database\Seeders\ApprovalReferenceSeeder;
use Database\Seeders\OutletClassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdplReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_workflow_follows_rm_nsm_gm_smd_dmd_and_is_idempotent(): void
    {
        $this->seed(ApprovalReferenceSeeder::class);
        $this->seed(ApprovalReferenceSeeder::class);

        $workflow = ApprovalWorkflow::query()->where('code', ApprovalWorkflow::CODE_DPL_STANDARD)->sole();

        $this->assertSame(
            ['RM', 'NSM', 'GM', 'SMD', 'DMD'],
            $workflow->steps()->with('role')->get()->pluck('role.name')->all(),
        );
        $this->assertSame([1, 2, 3, 4, 5], $workflow->steps->pluck('sequence')->all());
        $this->assertSame(5, Role::query()->whereIn('name', ['RM', 'NSM', 'GM', 'SMD', 'DMD'])->count());
    }

    public function test_workflow_reuses_existing_role_rows(): void
    {
        $existingRm = Role::factory()->create(['name' => 'RM']);

        $this->seed(ApprovalReferenceSeeder::class);

        $firstStep = ApprovalWorkflow::query()->where('code', ApprovalWorkflow::CODE_DPL_STANDARD)->sole()->steps->first();

        $this->assertSame($existingRm->id, $firstStep->role_id);
    }

    public function test_outlet_classification_seeder_is_idempotent(): void
    {
        $this->seed(OutletClassificationSeeder::class);
        $this->seed(OutletClassificationSeeder::class);

        $this->assertSame(['INSTANSI', 'RUMAH_SAKIT'], OutletCategory::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['NON_PEMERINTAH', 'PEMERINTAH'], OwnershipType::query()->orderBy('code')->pluck('code')->all());
    }
}
