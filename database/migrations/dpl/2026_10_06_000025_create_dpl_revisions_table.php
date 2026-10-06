<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot revisi proposal yang sama (pengganti legacy dpl_snapshot / dpl_item_log_updated).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->integer('revision_number');
            $table->text('reason')->nullable();
            $table->jsonb('snapshot');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['dpl_proposal_id', 'revision_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_revisions');
    }
};
