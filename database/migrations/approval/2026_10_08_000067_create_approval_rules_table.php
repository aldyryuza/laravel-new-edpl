<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan jalur approval per item (pengganti legacy dpl_config DPL_ITEM_APPROVAL_RULE).
 *
 * Cocok bila Total Calculator item > discount_lower (eksklusif, null = tanpa batas bawah) dan
 * <= discount_upper (inklusif, null = tanpa batas atas), dan provinsi kota outlet ada di daftar provinsi
 * aturan (tanpa provinsi = semua). Aturan dicek berurutan sort_order; yang pertama cocok dipakai.
 * Tidak ada aturan yang cocok = jalur lengkap (semua step workflow).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('discount_lower', 10, 4)->nullable()->comment('Batas bawah Total Calculator (eksklusif); null = tanpa batas');
            $table->decimal('discount_upper', 10, 4)->nullable()->comment('Batas atas Total Calculator (inklusif); null = tanpa batas');
            $table->integer('sort_order')->default(0);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('ALTER TABLE dpl.approval_rules ADD CONSTRAINT approval_rules_range_check CHECK (discount_lower IS NULL OR discount_upper IS NULL OR discount_upper > discount_lower)');

        Schema::create('dpl.approval_rule_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('dpl.approval_rules')->restrictOnDelete();
            $table->foreignId('workflow_step_id')->index()->constrained('dpl.approval_workflow_steps')->restrictOnDelete();

            $table->unique(['approval_rule_id', 'workflow_step_id']);
        });

        Schema::create('dpl.approval_rule_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('dpl.approval_rules')->restrictOnDelete();
            $table->foreignId('province_id')->index()->constrained('provinces')->restrictOnDelete();

            $table->unique(['approval_rule_id', 'province_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.approval_rule_provinces');
        Schema::dropIfExists('dpl.approval_rule_steps');
        Schema::dropIfExists('dpl.approval_rules');
    }
};
