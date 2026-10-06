<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan step workflow (standard: RM -> NSM -> GM -> SMD -> DMD).
 * Approver step memakai roles existing via role_id, bukan string nama role.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('dpl.approval_workflows')->restrictOnDelete();
            $table->integer('sequence');
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_reject')->default(true);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['workflow_id', 'sequence']);
        });

        DB::statement('ALTER TABLE dpl.approval_workflow_steps ADD CONSTRAINT approval_workflow_steps_sequence_check CHECK (sequence > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_workflow_steps');
    }
};
