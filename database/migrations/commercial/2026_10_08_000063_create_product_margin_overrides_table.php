<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Override margin per product (legacy dpl_config DPL_PRODUCT_MARGIN_OVERRIDE), diterapkan terakhir untuk item
 * non-Direct. Kosong = semua: distributor_group_code null = semua grup, team_code null = semua team.
 * Urutan cocok: team + grup, team, grup, semua.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dpl.product_margin_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->index()->constrained('products')->restrictOnDelete();
            $table->string('distributor_group_code', 50)->nullable()->comment('null = semua grup distributor');
            $table->string('team_code', 20)->nullable()->comment('null = semua team');
            $table->decimal('margin', 10, 4);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX product_margin_overrides_scope_unique ON dpl.product_margin_overrides (product_id, COALESCE(distributor_group_code, ''), COALESCE(team_code, ''))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dpl.product_margin_overrides');
    }
};
