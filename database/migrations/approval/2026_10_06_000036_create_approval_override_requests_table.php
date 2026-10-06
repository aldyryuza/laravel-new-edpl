<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request override/open lock (mis. calculator di atas threshold), pengganti legacy
 * dpl_req_open_lock dan log_unlock_rejected.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_override_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpl_proposal_id')->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->foreignId('dpl_item_id')->nullable()->index()->constrained('dpl.dpl_items')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('request_reason');
            $table->string('status', 30);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['dpl_proposal_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_override_requests');
    }
};
