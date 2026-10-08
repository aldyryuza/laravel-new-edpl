<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step "gerbang": step sesudahnya baru bisa bertindak setelah semua gerbang sebelumnya (di jalur item) approve.
 * Legacy: RM lalu NSM wajib berurutan; GM, SMD, DMD cukup menunggu NSM (boleh tidak berurutan / terlewati).
 * Default true = berurutan penuh; nilai per step di-set ApprovalReferenceSeeder (RM, NSM gerbang).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dpl.approval_workflow_steps', function (Blueprint $table) {
            $table->boolean('is_gate')->default(true)->after('is_required')->comment('Step sesudahnya menunggu step ini');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dpl.approval_workflow_steps', function (Blueprint $table) {
            $table->dropColumn('is_gate');
        });
    }
};
