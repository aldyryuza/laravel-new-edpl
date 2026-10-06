<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BR-012 / DBR-006: maksimal satu DPL ACTIVE per outlet + team (partial unique index).
 */
class DplActiveProposalConstraintTest extends TestCase
{
    use RefreshDatabase;

    private int $outletId;

    private int $userId;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = User::factory()->create()->id;
        $this->outletId = DB::table('outlets')->insertGetId(['code' => 'OUT-A', 'name' => 'Outlet A']);
    }

    public function test_second_active_dpl_for_same_outlet_and_team_is_rejected(): void
    {
        $this->insertProposal('A', 'ACTIVE');

        $this->expectException(QueryException::class);

        $this->insertProposal('A', 'ACTIVE');
    }

    public function test_different_teams_can_each_have_an_active_dpl_on_same_outlet(): void
    {
        $this->insertProposal('A', 'ACTIVE');
        $this->insertProposal('B', 'ACTIVE');

        $this->assertSame(2, DB::table('dpl.dpl_proposals')->where('program_status', 'ACTIVE')->count());
    }

    public function test_inactive_and_soft_deleted_dpl_do_not_block_a_new_active_dpl(): void
    {
        $this->insertProposal('A', 'INACTIVE');
        $this->insertProposal('A', 'EXPIRED');
        $this->insertProposal('A', 'ACTIVE', deletedAt: now());

        $this->insertProposal('A', 'ACTIVE');

        $this->assertSame(1, DB::table('dpl.dpl_proposals')->where('program_status', 'ACTIVE')->whereNull('deleted_at')->count());
    }

    private function insertProposal(string $teamCode, string $programStatus, mixed $deletedAt = null): void
    {
        DB::table('dpl.dpl_proposals')->insert([
            'dpl_number' => 'DPL-'.++$this->sequence,
            'outlet_id' => $this->outletId,
            'team_code' => $teamCode,
            'created_by_user_id' => $this->userId,
            'date_offer' => '2026-10-01',
            'date_start' => '2026-10-01',
            'date_end' => '2027-09-30',
            'program_status' => $programStatus,
            'deleted_at' => $deletedAt,
        ]);
    }
}
