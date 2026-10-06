<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State approval saat ini per DPL item per step (mendukung approval per item / partial approved).
 * History setiap aksi ada di approval_actions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_item_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_instance_id')->constrained('dpl.approval_step_instances')->restrictOnDelete();
            $table->foreignId('dpl_item_id')->index()->constrained('dpl.dpl_items')->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            // Juga melayani lookup per step_instance_id.
            $table->unique(['step_instance_id', 'dpl_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_item_states');
    }
};
