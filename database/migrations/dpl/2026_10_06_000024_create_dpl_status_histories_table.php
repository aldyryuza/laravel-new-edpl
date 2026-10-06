<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business history transisi status DPL (append-only di level aplikasi).
 * status_type: PROPOSAL (proposal_status) / PROGRAM (program_status).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->string('status_type', 30);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('reason')->nullable();
            // Nullable untuk transisi oleh sistem (mis. scheduler expired).
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dpl_proposal_id', 'created_at']);
            $table->index(['status_type', 'new_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_status_histories');
    }
};
