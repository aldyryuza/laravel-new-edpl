<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Kacab (kepala cabang) distributor untuk surat keluar, redesign dari legacy
 * dpl_exit_letter_master. valid_from/valid_until menjaga histori pergantian Kacab.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.distributor_branch_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->index()->constrained('distributors')->restrictOnDelete();
            $table->string('name', 255);
            $table->string('position', 150)->nullable()->comment('Jabatan');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE dpl.distributor_branch_heads ADD CONSTRAINT distributor_branch_heads_valid_period_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.distributor_branch_heads');
    }
};
