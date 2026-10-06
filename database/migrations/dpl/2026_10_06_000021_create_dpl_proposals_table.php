<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Header pengajuan DPL.
 *
 * proposal_status (status pengajuan/approval) dan program_status (aktivasi program) adalah
 * konsep terpisah. Nilai status divalidasi di aplikasi; CHECK nilai belum dipasang sampai
 * daftar state stabil (docs/Schema.md).
 *
 * Relasi amendment/renewal/consolidation disimpan di dpl_relations, bukan flag legacy
 * (ref_id_dpl, from_dpl_action, tag_make_a_copy).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('dpl_number', 50)->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->string('team_code', 20)->comment('Identitas team, legacy A/B');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('date_offer');
            $table->date('date_start');
            $table->date('date_end');
            $table->boolean('is_contract')->default(false);
            $table->integer('bed_count_snapshot')->nullable()->comment('Snapshot outlets.bed_count saat pengajuan');
            $table->string('proposal_type', 30)->default('NORMAL')->comment('NORMAL, CLEARANCE, CONSOLIDATION');
            $table->string('proposal_status', 30)->default('DRAFT')->comment('DRAFT, SUBMITTED, IN_APPROVAL, PARTIALLY_APPROVED, APPROVED, REJECTED, CANCELLED');
            $table->string('program_status', 30)->default('INACTIVE')->comment('INACTIVE, ACTIVE, EXPIRED, SUPERSEDED');
            $table->text('description')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Index komposit di bawah juga melayani lookup outlet_id, created_by_user_id, dan proposal_status.
            $table->index('program_status');
            $table->index('date_start');
            $table->index('date_end');
            $table->index(['outlet_id', 'program_status']);
            $table->index(['proposal_status', 'updated_at']);
            $table->index(['created_by_user_id', 'created_at']);
        });

        // BR-012: maksimal satu DPL ACTIVE per outlet + team. Partial unique index PostgreSQL.
        DB::statement("CREATE UNIQUE INDEX dpl_proposals_active_outlet_team_unique ON dpl.dpl_proposals (outlet_id, team_code) WHERE program_status = 'ACTIVE' AND deleted_at IS NULL");

        DB::statement('ALTER TABLE dpl.dpl_proposals ADD CONSTRAINT dpl_proposals_period_check CHECK (date_end >= date_start)');
        DB::statement('ALTER TABLE dpl.dpl_proposals ADD CONSTRAINT dpl_proposals_bed_count_snapshot_check CHECK (bed_count_snapshot IS NULL OR bed_count_snapshot >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS dpl.dpl_proposals_active_outlet_team_unique');

        Schema::dropIfExists('dpl.dpl_proposals');
    }
};
