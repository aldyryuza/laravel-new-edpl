<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet anggota consolidation beserta DPL per outlet yang dihasilkan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.dpl_consolidation_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidation_id')->constrained('dpl.dpl_consolidations')->restrictOnDelete();
            $table->foreignId('outlet_id')->index()->constrained('outlets')->restrictOnDelete();
            // Satu DPL hasil hanya milik satu baris consolidation outlet (ERD 1 : 0..1).
            $table->foreignId('generated_dpl_id')->nullable()->unique()->constrained('dpl.dpl_proposals')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['consolidation_id', 'outlet_id'], 'dpl_consolidation_outlets_consolidation_outlet_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.dpl_consolidation_outlets');
    }
};
