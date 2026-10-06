<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eksekusi workflow approval untuk satu DPL. Restart approval = instance baru;
 * instance lama tetap sebagai history.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->foreignId('workflow_id')->constrained('dpl.approval_workflows')->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['dpl_proposal_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_instances');
    }
};
