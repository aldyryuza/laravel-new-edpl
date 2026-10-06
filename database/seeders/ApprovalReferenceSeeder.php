<?php

namespace Database\Seeders;

use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Role approver E-DPL dan workflow standard RM -> NSM -> GM -> SMD -> DMD (BR-002).
 *
 * Idempotent: role dicari berdasarkan nama, step berdasarkan (workflow, sequence).
 * Approver per region/zone/global (approver_assignments) TIDAK di-seed karena berisi user production.
 */
class ApprovalReferenceSeeder extends Seeder
{
    /**
     * Urutan = sequence step. can_edit NSM mengikuti PRD (NSM dapat edit sesuai workflow rejection);
     * kapan edit diizinkan tetap ditentukan approval service.
     *
     * @var array<int, array{name: string, description: string, can_edit: bool}>
     */
    private const STEPS = [
        ['name' => 'RM', 'description' => 'Regional Manager - approver berdasarkan regional', 'can_edit' => false],
        ['name' => 'NSM', 'description' => 'National Sales Manager - approver berdasarkan zona', 'can_edit' => true],
        ['name' => 'GM', 'description' => 'General Manager - approver global', 'can_edit' => false],
        ['name' => 'SMD', 'description' => 'SMD - approver global', 'can_edit' => false],
        ['name' => 'DMD', 'description' => 'DMD - approver global (final)', 'can_edit' => false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workflow = ApprovalWorkflow::query()->firstOrCreate(
            ['code' => ApprovalWorkflow::CODE_DPL_STANDARD],
            ['name' => 'Approval DPL Standard', 'description' => 'RM -> NSM -> GM -> SMD -> DMD'],
        );

        foreach (self::STEPS as $index => $step) {
            $role = Role::query()->firstOrCreate(['name' => $step['name']], ['description' => $step['description']]);

            $workflow->steps()->updateOrCreate(
                ['sequence' => $index + 1],
                ['role_id' => $role->id, 'can_edit' => $step['can_edit'], 'can_reject' => true, 'is_required' => true],
            );
        }
    }
}
