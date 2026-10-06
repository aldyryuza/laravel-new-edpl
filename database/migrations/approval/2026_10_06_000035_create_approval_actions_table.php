<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event approval (APPROVE, REJECT, RETURN, CANCEL, OVERRIDE). Append-only: baris lama tidak
 * di-update/di-delete. dpl_item_id null = aksi level step/proposal.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_instance_id')->constrained('dpl.approval_step_instances')->restrictOnDelete();
            $table->foreignId('dpl_item_id')->nullable()->index()->constrained('dpl.dpl_items')->restrictOnDelete();
            $table->foreignId('actor_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['step_instance_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_actions');
    }
};
