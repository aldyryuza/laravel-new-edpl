<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step approval aktual per instance. assigned_user_id adalah SNAPSHOT approver hasil resolusi
 * saat instance dibuat; perubahan approver_assignments setelahnya tidak mengubah baris ini.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_step_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_instance_id')->constrained('dpl.approval_instances')->restrictOnDelete();
            $table->foreignId('workflow_step_id')->constrained('dpl.approval_workflow_steps')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_instance_id', 'workflow_step_id'], 'approval_step_instances_instance_step_unique');
            // Antrian approval; juga melayani lookup per assigned_user_id.
            $table->index(['assigned_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_step_instances');
    }
};
