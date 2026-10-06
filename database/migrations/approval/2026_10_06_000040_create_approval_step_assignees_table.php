<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu role + scope boleh punya lebih dari satu approver aktif (mis. 2 RM dalam satu region);
 * cukup salah satu yang bertindak. Snapshot kandidat approver per step dipindah dari kolom tunggal
 * approval_step_instances.assigned_user_id ke tabel approval_step_assignees.
 * Pelaku aksi sebenarnya tercatat di approval_actions.actor_user_id.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_step_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_instance_id')->constrained('dpl.approval_step_instances')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['step_instance_id', 'user_id'], 'approval_step_assignees_step_user_unique');
            // Antrian approval per user.
            $table->index('user_id');
        });

        DB::statement('INSERT INTO dpl.approval_step_assignees (step_instance_id, user_id, created_at) SELECT id, assigned_user_id, created_at FROM dpl.approval_step_instances');

        Schema::table('dpl.approval_step_instances', function (Blueprint $table) {
            $table->dropIndex(['assigned_user_id', 'status']);
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn('assigned_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Kembali ke satu approver per step: bila ada beberapa kandidat, yang tersimpan hanya user dengan id terkecil.
     */
    public function down(): void
    {
        Schema::table('dpl.approval_step_instances', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('workflow_step_id')->constrained('users')->restrictOnDelete();
            $table->index(['assigned_user_id', 'status']);
        });

        DB::statement('UPDATE dpl.approval_step_instances s SET assigned_user_id = (SELECT MIN(a.user_id) FROM dpl.approval_step_assignees a WHERE a.step_instance_id = s.id)');

        Schema::dropIfExists('dpl.approval_step_assignees');
    }
};
